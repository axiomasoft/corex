<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Types\Contracts\FieldTypes;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\Connection;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final readonly class SchemaPlanner
{
    public function __construct(private DatabaseSchemaHeads $heads, private FieldTypes $types, private DirectDdlRoute $route, private SchemaReferences $references) {}

    /** @return array<string,mixed> */
    public function build(Connection $db, DataScope $scope, stdClass $head, FieldSchemaChange $change, string $ref): array
    {
        $image = new SchemaImage(SchemaRows::decode($head->mapping));
        $id = $change->field->value;
        $old = isset($image->entries[$id]) ? $image->entry($id) : null;
        $from = $old === null ? null : $image->definition($id);

        if (($change->kind === 'add') !== ($old === null)) {
            throw new SchemaConflict('field-identity-conflict');
        }

        if ($old === null && SchemaRows::rows(db: $db, table: SchemaRows::DEFINITIONS, scope: $scope, entity: $change->entity->value)->where('field_id', $id)->exists()) {
            throw new SchemaConflict('retired-field-identity');
        }
        $code = $this->heads->declared(db: $db, scope: $scope, entity: $change->entity);
        $declared = isset($code->entries[$id]) ? $code->definition($id) : null;
        $to = $change->definition;

        if ($from !== null) {
            $data = ($to ?? $from)->toArray();
            $data['revision'] = max($from->revision + 1, $to->revision ?? 0);
            $data['machine_key'] = $change->machineKey ?? $data['machine_key'];
            $data['lifecycle'] = $change->kind === 'retire' ? 'retired' : 'active';
            $to = SchemaImage::hydrate($data);
        }
        $to ??= throw new SchemaConflict('missing-target-definition');

        if ($from !== null && $from->owner !== $to->owner) {
            throw new SchemaConflict('field-owner-conflict');
        }
        $source = $old['source'] ?? ($declared === null ? 'runtime' : 'code');

        if ($source === 'code') {
            if ($change->kind === 'retire' ? $declared !== null : ($declared === null || $declared->toArray() !== $to->toArray())) {
                throw new SchemaConflict('declared-field-owner-conflict');
            }
        } elseif ($to->owner !== 'runtime' || $declared !== null) {
            throw new SchemaConflict('runtime-field-owner-conflict');
        }

        if ($change->kind !== 'retire' && $to->lifecycle !== 'active') {
            throw new SchemaConflict('target-field-inactive');
        }
        $this->types->resolve(type: $to->type, version: $to->codecVersion);
        $representation = $from !== null && ($from->type->value !== $to->type->value || $from->codecVersion !== $to->codecVersion
            || $from->localizationPolicy !== $to->localizationPolicy);

        if ($representation && ($change->converterId === null || $change->converterVersion === null)) {
            throw new SchemaConflict('versioned-converter-required');
        }

        if ($change->converterId !== null && $change->converterVersion !== null) {
            $this->types->converter(id: $change->converterId, version: $change->converterVersion);
        }
        $shadow = $from !== null && $change->kind === 'revise' && ($representation || $change->converterId !== null
            || $from->nullable !== $to->nullable || $from->constraints !== $to->constraints);
        $storage = $old === null ? $to->revision : ($shadow ? $to->revision : SchemaValue::integer($old['storage_revision']));
        $oldIndex = SchemaValue::nullableText($old['index_operation'] ?? null);
        $needsIndex = $change->kind !== 'retire' && $to->indexPolicy === 'expression_btree';
        $newIndex = $needsIndex ? ($oldIndex !== null && ! $shadow ? $oldIndex : $ref) : null;
        $image->put(field: $to, source: SchemaValue::text($source), storageRevision: $storage, indexOperation: $newIndex);
        $targetEntry = $image->entry($id);

        if ($change->kind === 'retire') {
            unset($image->entries[$id]);
        }
        $keys = [];
        foreach (array_keys($image->entries) as $other) {
            $key = $image->definition($other)->machineKey;

            if (isset($keys[$key])) {
                throw new SchemaConflict('machine-key-conflict');
            }
            $keys[$key] = true;
        }
        // A code deployment can publish exactly the declaration that was compiled into the current manifest.
        $projectedCode = new SchemaImage;
        foreach (array_keys($image->entries) as $other) {
            if ($image->entry($other)['source'] === 'code') {
                $field = $image->definition($other);
                $projectedCode->put(field: $field, source: 'code', storageRevision: $field->revision);
            }
        }
        $ddl = $newIndex === $ref ? IndexCatalogue::plan(db: $db, scope: $scope, field: $to, slot: SchemaValue::text($targetEntry['slot']), namespace: $this->route->namespace(db: $db, scope: $scope)) : null;

        return ['field' => $id, 'kind' => $change->kind, 'entity' => $change->entity->value, 'change' => $change->toArray(),
            'base' => $head->effective_revision, 'base_mapping_digest' => $head->mapping_digest, 'target' => SchemaRows::digest(['schema.revision.v2', $ref, $image->digest()]),
            'mapping_digest' => $image->digest(), 'manifest_digest' => $projectedCode->digest(), 'deployed_manifest_digest' => $code->digest(), 'mapping' => $image->entries, 'from' => $old, 'to' => $targetEntry,
            'shadow' => $shadow, 'converter_id' => $change->converterId, 'converter_version' => $change->converterVersion,
            'dependencies' => $this->references->get(scope: $scope, entity: $change->entity, field: $id, revision: SchemaValue::text($head->effective_revision)),
            'failure_policy' => 'block-until-corrected', 'ddl' => $ddl, 'drop_origin' => $oldIndex !== $newIndex ? $oldIndex : null,
            'estimated_rows' => SchemaRows::rows(db: $db, table: SchemaRows::DOCUMENTS, scope: $scope, entity: $change->entity->value)->count()];
    }
}
