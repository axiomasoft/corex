<?php

declare(strict_types=1);

namespace Flex\Modules\Compiler;

use Flex\Modules\Extend\EntityField;

/**
 * An EntityField extender resolved against the compiled entity set (P1.4).
 * `sleeping` = declaring module differs from the target entity's owning
 * module (B-10 §7.4 п.5): the declaration is collected here regardless, but
 * only takes effect for a tenant where the target module is active — that
 * per-tenant filter is ModuleRegistry's job (P1.5), not this compiler's.
 */
final readonly class CompiledEntityField
{
    public function __construct(
        public EntityField $extender,
        public string $declaringModule,
        public bool $sleeping,
    ) {}

    /**
     * Plain-array projection for the compiled file (P1.15). The wrapped
     * {@see EntityField} extender flattens through the shared
     * {@see CompiledRegistry::extenderToArray()} — no `__set_state`.
     *
     * @return array{extender: array<string, mixed>, declaringModule: string, sleeping: bool}
     */
    public function toArray(): array
    {
        return [
            'extender' => CompiledRegistry::extenderToArray($this->extender),
            'declaringModule' => $this->declaringModule,
            'sleeping' => $this->sleeping,
        ];
    }

    /**
     * @param  array{extender: array<string, mixed>, declaringModule: string, sleeping: bool}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            extender: CompiledRegistry::extenderFromArray(EntityField::class, $data['extender']),
            declaringModule: $data['declaringModule'],
            sleeping: $data['sleeping'],
        );
    }
}
