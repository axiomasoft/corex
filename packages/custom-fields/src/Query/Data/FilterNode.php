<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Query\Data;

use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FilterNode
{
    public function __construct(public FieldId $field, public FieldOperator $operator, public ?FieldInput $operand = null)
    {
        if (in_array($operator, [FieldOperator::IsNull, FieldOperator::IsAbsent], true) !== ($operand === null)) {
            throw new InvalidArgumentException('Field filter operand has the wrong arity.');
        }
    }
}
