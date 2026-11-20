<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\CustomFields\Schemas\Contracts\SchemaHeads;
use CoreX\CustomFields\Schemas\Data\FieldSchemaSnapshot;
use CoreX\CustomFields\Schemas\Data\LockedFieldMapping;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final readonly class DatabaseSchemaHeads implements SchemaHeads
{
    public function __construct(private CurrentWorkScope $current, private DeclaredSchema $declared) {}

    public function lockForWrite(ConnectionInterface $db, DataScope $scope, EntityHandle $entity): LockedFieldMapping
    {
        return $this->mapping($this->row($db, $scope, $entity), $entity);
    }

    public function effective(ConnectionInterface $db, DataScope $scope, EntityHandle $entity): LockedFieldMapping
    {
        return $this->lockForWrite($db, $scope, $entity);
    }

    public function row(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, bool $exclusive = false, bool $allowManifestChange = false): stdClass
    {
        SchemaRows::transaction($db);
        $declared = $this->declared->image($this->current->assert($scope, $db), $entity);
        $key = [...SchemaRows::logical($scope), 'entity' => $entity->value];
        $query = $db->table('sys_field_schema_heads')->where($key);
        $lock = $exclusive ? 'for update' : 'for share';
        $row = (clone $query)->lock($lock)->first();

        if ($row === null) {
            $digest = $declared->digest();
            $inserted = $db->table('sys_field_schema_heads')->insertOrIgnoreReturning([...$key, 'mapping' => SchemaRows::json($declared->entries),
                'effective_revision' => $digest, 'mapping_digest' => $digest, 'manifest_digest' => $digest], ['entity'], ['account_id', 'account_incarnation', 'workspace_scope', 'entity']);

            if ($inserted->isNotEmpty()) {
                foreach (array_keys($declared->entries) as $id) {
                    $this->publishDefinition($db, $scope, $entity, $declared, $id);
                    $this->reserve($db, $scope, $entity, $declared, $id, null);
                }
            }
            $row = (clone $query)->lock($lock)->first() ?? throw new SchemaConflict('head-bootstrap-conflict');
        }

        if (! $allowManifestChange && $row->manifest_digest !== $declared->digest()) {
            // A deployed revision is prospective until switch. A vanished contributor revokes eligibility immediately.
            $active = new SchemaImage(SchemaRows::decode($row->mapping));
            foreach (array_keys($active->entries) as $id) {
                if ($active->entry($id)['source'] === 'code' && (! isset($declared->entries[$id]) || $declared->definition($id)->owner !== $active->definition($id)->owner)) {
                    throw new SchemaConflict('declaring-owner-unavailable');
                }
            }
        }

        return $row;
    }

    public function declared(ConnectionInterface $db, DataScope $scope, EntityHandle $entity): SchemaImage
    {
        return $this->declared->image($this->current->assert($scope, $db), $entity);
    }

    public function publishDefinition(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, SchemaImage $image, string $id): void
    {
        $definition = $image->definition($id);
        $entry = $image->entry($id);
        $key = [...SchemaRows::logical($scope), 'entity' => $entity->value, 'field_id' => $id, 'revision' => $definition->revision];
        $digest = SchemaRows::digest($entry);
        $db->table('sys_attribute_definitions')->insertOrIgnore([...$key, 'storage_revision' => $entry['storage_revision'], 'owner' => $definition->owner,
            'provenance' => $entry['source'], 'definition' => SchemaRows::json($definition->toArray()), 'definition_digest' => $digest]);

        if ($db->table('sys_attribute_definitions')->where($key)->value('definition_digest') !== $digest) {
            throw new SchemaConflict('immutable-definition-conflict');
        }
    }

    public function reserve(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, SchemaImage $image, string $id, ?string $operation): void
    {
        $key = [...SchemaRows::logical($scope), 'entity' => $entity->value, 'machine_key' => $image->definition($id)->machineKey];
        $db->table('sys_attribute_key_reservations')->insertOrIgnore([...$key, 'field_id' => $id, 'operation_id' => $operation]);

        if ($db->table('sys_attribute_key_reservations')->where($key)->value('field_id') !== $id) {
            throw new SchemaConflict('machine-key-reserved');
        }
    }

    private function mapping(stdClass $row, EntityHandle $entity): LockedFieldMapping
    {
        $image = new SchemaImage(SchemaRows::decode($row->mapping));
        $fields = $slots = $shadows = $shadowSlots = [];
        foreach (array_keys($image->entries) as $id) {
            $fields[$id] = $image->definition($id);
            $slots[$id] = SchemaValue::text($image->entry($id)['slot']);
        }
        $dual = $row->dual_write === null ? [] : SchemaRows::decode($row->dual_write);

        if ($dual !== []) {
            $id = SchemaValue::text($dual['field']);
            $shadows[$id] = SchemaImage::hydrate(SchemaValue::map($dual['definition']));
            $shadowSlots[$id] = SchemaValue::text($dual['slot']);
        }

        return new LockedFieldMapping(new FieldSchemaSnapshot($entity, new SchemaRevision(SchemaValue::text($row->effective_revision)),
            SchemaValue::text($row->mapping_digest), $fields), $slots, $shadows, $shadowSlots,
            SchemaValue::nullableText($dual['converter_id'] ?? null), isset($dual['converter_version']) ? SchemaValue::integer($dual['converter_version']) : null);
    }
}
