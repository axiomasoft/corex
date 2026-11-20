<?php

declare(strict_types=1);

namespace CoreX\Search\Rebuild\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class RebuildPlan
{
    public function __construct(public RebuildPlanRef $ref, public string $mappingDigest, public ?int $expectedGeneration) {}
}
