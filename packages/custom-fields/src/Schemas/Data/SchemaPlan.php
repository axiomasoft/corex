<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SchemaPlan
{
    /**
     * @param  list<string>  $blockers
     * @param  list<string>  $dependencies
     */
    public function __construct(public SchemaPlanRef $ref, public PlanDigest $digest, public SchemaRevision $base, public SchemaRevision $target, public array $blockers = [], public int $estimatedRows = 0, public ?FieldSchemaChange $change = null, public ?PlanDigest $ddlDigest = null, public array $dependencies = [], public string $conversionFailurePolicy = 'block-until-corrected') {}
}
