<?php

declare(strict_types=1);

namespace Flex\Modules\Registry;

/**
 * System field of an entity (B-10 §3.4); custom fields live in flex/attributes.
 */
final readonly class FieldDefinition
{
    /**
     * @param  'string'|'int'|'decimal'|'bool'|'date'|'datetime'|'json'|'money'|'relation'|'enum'  $type
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public string $handle,
        public string $type,
        public bool $required = false,
        public bool $searchable = false,
        public ?array $meta = null,
    ) {}
}
