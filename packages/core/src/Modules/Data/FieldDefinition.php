<?php

declare(strict_types=1);

namespace CoreX\Modules\Data;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;

/**
 * System field of an entity; custom fields live in an external attributes package.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class FieldDefinition
{
    /**
     * @param  'string'|'int'|'decimal'|'bool'|'date'|'datetime'|'json'|'money'|'relation'|'enum'  $type
     */
    public function __construct(
        public string $handle,
        public string $type,
        public bool $required = false,
        public bool $searchable = false,
        public ?JsonDocument $meta = null,
    ) {}

    /**
     * Plain-array projection for {@see CompiledRegistry} persistence — scalars
     * only, no `__set_state`: the compiled file must be var_export'able and
     * re-readable without reconstructing objects from serialized state.
     *
     * @return array{handle: string, type: string, required: bool, searchable: bool, meta: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'handle' => $this->handle,
            'type' => $this->type,
            'required' => $this->required,
            'searchable' => $this->searchable,
            'meta' => JsonDocuments::optionalMap($this->meta),
        ];
    }

    /**
     * @param  array{handle: string, type: 'string'|'int'|'decimal'|'bool'|'date'|'datetime'|'json'|'money'|'relation'|'enum', required?: bool, searchable?: bool, meta?: array<string, mixed>|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            handle: $data['handle'],
            type: $data['type'],
            required: $data['required'] ?? false,
            searchable: $data['searchable'] ?? false,
            meta: JsonDocuments::nullable($data['meta'] ?? null),
        );
    }
}
