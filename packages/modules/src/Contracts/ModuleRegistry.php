<?php

declare(strict_types=1);

namespace CoreX\Modules\Contracts;

use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Exceptions\ModuleNotFound;
use CoreX\Modules\Registry\CompiledModule;
use CoreX\Tenancy\TenantContext;

/**
 * Compiled code-level registry (B-10 §3.3/§5.2): built by
 * `flex:modules:compile` at deploy time, read from a file cache — 0
 * filesystem scans, 0 SQL queries on a warm hot path.
 */
interface ModuleRegistry
{
    /** @return list<CompiledModule> every module in the codebase */
    public function all(): array;

    /** @throws ModuleNotFound */
    public function get(string $name): CompiledModule;

    /** @return list<CompiledModule> intersection with the tenant's enabled set (mod_modules) */
    public function active(TenantContext $ctx): array;

    public function isActive(string $name, TenantContext $ctx): bool;

    /**
     * The full compiled registry (all 10 slices — B-10 §5.2, P1.25 added
     * `settings`/`featureFlags`), read from the
     * same compiled file (P1.15). The runtime projects `entities` into
     * EntityRegistry and the morph map from this; downstream consumers of the
     * other slices live outside this package. Exposed as the whole
     * {@see CompiledRegistry} (not just `entities()`) because the projection,
     * morph map and P1.18 lifecycle all read different slices of it.
     */
    public function compiled(): CompiledRegistry;
}
