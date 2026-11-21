<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldProjectionSchema
{
    /** @param array<string,FieldProjectionShape> $fields */
    public function __construct(public SchemaRevision $revision, public string $mappingDigest, public array $fields)
    {
        new PlanDigest($mappingDigest);
    }
}
