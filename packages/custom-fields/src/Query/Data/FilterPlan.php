<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Query\Data;

use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FilterPlan
{
    /** @param ?list<string> $recordIds */
    public function __construct(public EntityHandle $entity, public FieldId $field, public FieldOperator $operator, public ?JsonDocument $operand, public SchemaRevision $schemaRevision, public string $mappingDigest, public string $scopeFingerprint, public string $policyWitness, public ?array $recordIds) {}
}
