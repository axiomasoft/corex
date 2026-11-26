<?php

declare(strict_types=1);

namespace CoreX\Access\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Publishes a native role matrix for one role inside a tenant transaction.
 * The scope-map version changes after the transaction commits.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RoleMatrixCompiler
{
    public function compile(string $roleId): void;

    /** Re-runs compile() for every non-deleted role — after a module install/enable. */
    public function recompileAll(): void;
}
