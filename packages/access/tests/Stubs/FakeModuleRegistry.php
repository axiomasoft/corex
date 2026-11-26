<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Stubs;

use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Compiler\CompiledModule;
use Packman\Modules\Data\Compiler\CompiledRegistry;
use Packman\Modules\Data\Extenders\Permissions;
use Packman\Modules\Exceptions\Registry\ModuleNotFound;

/**
 * Test double for ModuleRegistry. The real, file-backed
 * `CompiledModuleRegistry` reports `isActive()` false for everything when no
 * `bootstrap/cache/corex_modules.php` exists — which Testbench never writes
 * — so the active-module filter (`ActiveEntityFilter`, `PermissionsMerger`)
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

    public function active(ContextScopeInterface $ctx): array
    {
        return [];
    }

    public function isActive(string $name, ContextScopeInterface $ctx): bool
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
