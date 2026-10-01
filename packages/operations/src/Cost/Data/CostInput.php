<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class CostInput
{
    public function __construct(
        public int $pageRows,
        public int $selectedFields,
        public int $relationNodes,
        public int $attributeFilters,
        public bool $highlight,
        public int $exportRows,
        public int $sideEffects,
    ) {
        foreach ([$pageRows, $selectedFields, $relationNodes, $attributeFilters, $exportRows, $sideEffects] as $count) {
            if ($count < 0) {
                throw new InvalidArgumentException('Cost input counts must be nonnegative.');
            }
        }
    }
}
