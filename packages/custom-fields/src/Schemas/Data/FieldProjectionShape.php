<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use CoreX\CustomFields\Types\Data\FieldOperatorSet;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\Data\Enums\FieldUse;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldProjectionShape
{
    /** @param list<FieldUse> $uses */
    public function __construct(public FieldId $id, public FieldTypeId $type, public array $uses, public ?FieldOperatorSet $operators, public bool $localized) {}
}
