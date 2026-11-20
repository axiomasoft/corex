<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Data;

use CoreX\CustomFields\Types\Enums\FieldOperator;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldOperatorSet
{
    /** @param list<FieldOperator> $operators */
    public function __construct(public array $operators)
    {
        FieldList::check($operators, [FieldOperator::class]);

        if (count(array_unique(array_column($operators, 'value'))) !== count($operators)) {
            throw new InvalidArgumentException('Invalid field operators.');
        }
    }
}
