<?php

declare(strict_types=1);

namespace CoreX\Auth\Tests\Stubs;

use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Exceptions\ModuleNotFound;
use CoreX\Modules\Extend\Permissions;
use CoreX\Modules\Registry\CompiledModule;
use CoreX\Tenancy\TenantContext;

/**
 * Test double for ModuleRegistry. The real, file-backed
 * `CompiledModuleRegistry` reports `isActive()` false for everything when no
 * `bootstrap/cache/corex_modules.php` exists — which Testbench never writes
 * — so P2.21's active-module filter (`ActiveEntityFilter`, `PermissionsMerger`)
 * needs an explicit, controllable stand-in rather than the real compiled
 * file, mirroring the `FakeTenancyManager`/`FakeWorkspaceScope` precedent in
 * this directory.
 */
final class FakeModuleRegistry implements ModuleRegistry
{
    private readonly CompiledRegistry $registry;

    /**
     * @param  list<string>  $active  Composer names considered active by isActive().
     * @param  list<Permissions>  $permissions  Declarations returned by compiled()->permissions.
     */
    public function __construct(
        private array $active = [],
        array $permissions = [],
    ) {
        $this->registry = new CompiledRegistry(
            entities: [],
            entityFields: [],
            permissions: $permissions,
            api: [],
            filament: [],
            menu: [],
            routes: [],
            workflow: [],
        );
    }

    public function all(): array
    {
        return [];
    }

    public function get(string $name): CompiledModule
    {
        throw new ModuleNotFound($name);
    }

    public function active(TenantContext $ctx): array
    {
        return [];
    }

    public function isActive(string $name, TenantContext $ctx): bool
    {
        return in_array($name, $this->active, true);
    }

    public function compiled(): CompiledRegistry
    {
        return $this->registry;
    }

    /** @param  list<string>  $active */
    public function setActive(array $active): void
    {
        $this->active = $active;
    }
}
