<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Types\Data;

use CoreX\CustomFields\Types\Contracts\FieldCodec;
use CoreX\Data\Enums\FieldUse;
use InvalidArgumentException;
use Packman\Attributes\Api;

/** @phpstan-import-type Json8 from FieldInput */
#[Api(since: '2.0.0')]
final readonly class FieldTypeDescriptor
{
    /**
     * @param  list<FieldUse>  $uses
     * @param  array<string,Json8>  $limits
     * @param  array<string, int>  $conversions
     */
    public function __construct(public string $codecId, public int $codecVersion, public FieldCodec $codec, public array $uses, public bool $nullable = true, public bool $multiple = false, public array $limits = [], public array $conversions = [])
    {
        FieldList::check($uses, [FieldUse::class]);

        if ($codecId === '' || $codecVersion < 1) {
            throw new InvalidArgumentException('Invalid field type descriptor.');
        }
    }
}
