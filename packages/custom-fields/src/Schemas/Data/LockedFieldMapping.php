<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Data;

use Packman\Attributes\Internal;

#[Internal]
final readonly class LockedFieldMapping
{
    /**
     * @param  array<string,string>  $slots
     * @param  array<string,FieldDefinition>  $shadows
     * @param  array<string,string>  $shadowSlots
     */
    public function __construct(public FieldSchemaSnapshot $schema, public array $slots, public array $shadows = [], public array $shadowSlots = [], public ?string $converterId = null, public ?int $converterVersion = null) {}
}
