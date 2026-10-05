<?php

declare(strict_types=1);

namespace CoreX\Access\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/**
 * Publishes a native role matrix for one role inside a tenant transaction.
 * The scope-map version changes after the transaction commits.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RoleMatrixCompiler
{
    public function compile(string $roleId): void;

    /** Re-runs compile() for every non-deleted role — after a module install/enable (B-11 §4 п.2). */
    public function recompileAll(): void;
}
