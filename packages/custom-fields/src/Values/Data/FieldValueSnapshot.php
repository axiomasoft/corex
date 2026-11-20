<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Values\Data;

use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\Data\Data\RecordRef;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldValueSnapshot
{
    /** @param array<string,FieldValue> $values */
    public function __construct(public RecordRef $record, public SchemaRevision $schemaRevision, public DocumentRevision $valueRevision, public array $values) {}
}
