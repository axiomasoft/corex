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
}
