<?php

declare(strict_types=1);

namespace CoreX\Access\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/**
 * Compiles aut_role_matrix rows into AzGuard's az_guard_role_permissions
 * (permission keys `{panel}.{entity_key}.{action}`, D100) — one role at a
 * time, transactionally (B-11 §7.3 п.8): AzGuard keys and the scope-map
 * version never diverge, the version bumps last.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RoleMatrixCompiler
{
    public function compile(string $roleId): void;

    /** Re-runs compile() for every non-deleted role — after a module install/enable (B-11 §4 п.2). */
    public function recompileAll(): void;
}
