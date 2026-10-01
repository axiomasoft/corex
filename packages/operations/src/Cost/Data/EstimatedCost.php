<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class EstimatedCost
{
    public function __construct(
        public int $units,
        public string $recipeHash,
    ) {
        if ($units < 1) {
            throw new InvalidArgumentException('Estimated cost units must be at least 1.');
        }

        if (preg_match('/^[a-f0-9]{64}$/D', $recipeHash) !== 1) {
            throw new InvalidArgumentException('Recipe hash must be SHA-256 hex.');
        }
    }
}
