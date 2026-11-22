<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldList;
use CoreX\CustomFields\Types\Data\FieldTypeDescriptor;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Enums\FieldUse;
use InvalidArgumentException;
use Packman\Attributes\Api;

/**
 * @phpstan-import-type Json8 from FieldInput
 *
 * @phpstan-type FieldMetadata array<string,Json8>
 * @phpstan-type DefinitionRow array{id:string, entity:string, machine_key:string, type:string, owner:string, codec_version:int, revision:int, nullable:bool, constraints:FieldMetadata, uses:list<string>, presentation:FieldMetadata, index_policy:string, localization_policy:string, lifecycle:string}
 */
#[Api(since: '2.0.0')]
final readonly class FieldDefinition
{
    /**
     * @param  FieldMetadata  $constraints
     * @param  list<FieldUse>  $uses
     * @param  FieldMetadata  $presentation
     */
    public function __construct(
        public FieldId $id,
        public EntityHandle $entity,
        public string $machineKey,
        public FieldTypeId $type,
        public string $owner,
        public int $codecVersion = 1,
        public int $revision = 1,
        public bool $nullable = true,
        public array $constraints = [],
        public array $uses = [],
        public array $presentation = [],
        public string $indexPolicy = 'none',
        public string $localizationPolicy = 'none',
        public string $lifecycle = 'active',
    ) {
        FieldList::check(values: $uses, classes: [FieldUse::class]);

        if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $machineKey) !== 1 || $owner === '' || strlen($owner) > 191
            || $codecVersion < 1 || $revision < 1
            || count(array_unique(array_column($uses, 'value'))) !== count($uses)
            || ! in_array($lifecycle, ['active', 'retiring', 'retired'], true)
            || ! in_array($indexPolicy, ['none', 'expression_btree', 'promoted'], true)) {
            throw new InvalidArgumentException('Invalid field definition.');
        }
    }

    public function permits(FieldUse $use, FieldTypeDescriptor $descriptor): bool
    {
        if (! in_array($use, $this->uses, true) || ! in_array($use, $descriptor->uses, true)) {
            return false;
        }

        if ($this->localizationPolicy !== 'none' && in_array($use, [FieldUse::Sort, FieldUse::Search, FieldUse::Aggregate], true)) {
            return false;
        }

        return $this->type->value !== 'money' || ! in_array($use, [FieldUse::Sort, FieldUse::Aggregate], true)
            || (is_string($this->constraints['currency'] ?? null) && preg_match('/\A[A-Z]{3}\z/D', $this->constraints['currency']) === 1);
    }

    /** @return DefinitionRow */
    public function toArray(): array
    {
        $uses = array_column($this->uses, 'value');
        sort($uses, SORT_STRING);

        return ['id' => $this->id->value, 'entity' => $this->entity->value, 'machine_key' => $this->machineKey,
            'type' => $this->type->value, 'owner' => $this->owner, 'codec_version' => $this->codecVersion, 'revision' => $this->revision,
            'nullable' => $this->nullable, 'constraints' => $this->constraints, 'uses' => $uses,
            'presentation' => $this->presentation, 'index_policy' => $this->indexPolicy, 'localization_policy' => $this->localizationPolicy, 'lifecycle' => $this->lifecycle];
    }

    /** @param DefinitionRow $row */
    public static function fromArray(array $row): self
    {
        return new self(id: new FieldId($row['id']), entity: new EntityHandle($row['entity']), machineKey: $row['machine_key'], type: new FieldTypeId($row['type']),
            owner: $row['owner'], codecVersion: $row['codec_version'], revision: $row['revision'], nullable: $row['nullable'], constraints: $row['constraints'], uses: array_map(FieldUse::from(...), $row['uses']),
            presentation: $row['presentation'], indexPolicy: $row['index_policy'], localizationPolicy: $row['localization_policy'], lifecycle: $row['lifecycle']);
    }
}
