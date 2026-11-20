<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use CoreX\CustomFields\Types\Data\FieldOperatorSet;
use CoreX\Data\Data\EntityHandle;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldSchemaSnapshot
{
    /**
     * @param  array<string,FieldDefinition>  $fields
     * @param  array<string,FieldOperatorSet>  $operators  Authorized operators keyed by field UUID.
     */
    public function __construct(public EntityHandle $entity, public SchemaRevision $revision, public string $mappingDigest, public array $fields, public array $operators = [])
    {
        new PlanDigest($mappingDigest);
        foreach ($fields as $id => $field) {
            if ($field->id->value !== $id || $field->entity->value !== $entity->value) {
                throw new InvalidArgumentException('Invalid schema snapshot identity.');
            }
        }
    }

    public function field(string $machineKey): FieldDefinition
    {
        return array_find($this->fields, static fn (FieldDefinition $field): bool => $field->machineKey === $machineKey)
            ?? throw new InvalidArgumentException('Field unavailable.');
    }
}
