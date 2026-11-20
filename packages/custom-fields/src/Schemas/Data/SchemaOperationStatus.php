<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SchemaOperationStatus
{
    /**
     * @param  list<string>  $blockers
     * @param  list<array{record_id:string,seen_revision:string,error_code:string}>  $failures
     */
    public function __construct(public SchemaOperationRef $ref, public string $state, public ClaimGeneration $generation, public array $blockers = [], public array $failures = [], public ?string $checkpoint = null) {}
}
