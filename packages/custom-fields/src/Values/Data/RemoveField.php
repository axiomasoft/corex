<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Values\Data;

use CoreX\CustomFields\Schemas\Data\FieldId;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class RemoveField
{
    public function __construct(public FieldId $field) {}
}
