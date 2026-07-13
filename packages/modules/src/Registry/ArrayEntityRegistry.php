<?php

declare(strict_types=1);

namespace Flex\Modules\Registry;

use Closure;
use CoreX\Tenancy\TenantContext;
use Flex\Modules\Contracts\EntityRegistry;
use Flex\Modules\Enums\Capability;
use Flex\Modules\Exceptions\EntityNotFound;

/**
 * In-memory EntityRegistry. Module providers register() their entities at
 * boot; the compiled hot-path registry (P1.5) replaces this for production
 * reads once RegistryCompiler (P1.4) exists.
 */
final class ArrayEntityRegistry implements EntityRegistry
{
    /** @var array<string, EntityDefinition> */
    private array $entities = [];

    /** @var Closure(string): bool */
    private readonly Closure $moduleActive;

    /**
     * @param  (Closure(string): bool)|null  $moduleActive  Seam for the real active-module
     *                                                      predicate (ModuleRegistry, P1.5).
     */
    public function __construct(?Closure $moduleActive = null)
    {
        $this->moduleActive = $moduleActive ?? static fn (string $module): bool => true;
    }

    public function register(EntityDefinition $definition): void
    {
        $this->entities[$definition->handle] = $definition;
    }

    public function get(string $handle): EntityDefinition
    {
        return $this->entities[$handle] ?? throw new EntityNotFound($handle);
    }

    public function has(string $handle): bool
    {
        return isset($this->entities[$handle]);
    }

    public function all(?TenantContext $ctx = null): array
    {
        return array_values(array_filter(
            $this->entities,
            fn (EntityDefinition $definition): bool => ($this->moduleActive)($definition->module),
        ));
    }

    public function byCapability(Capability $cap, ?TenantContext $ctx = null): array
    {
        return array_values(array_filter(
            $this->all($ctx),
            fn (EntityDefinition $definition): bool => in_array($cap, $definition->capabilities, true),
        ));
    }

    public function forModel(string $modelClass): ?EntityDefinition
    {
        foreach ($this->entities as $definition) {
            if ($definition->model === $modelClass) {
                return $definition;
            }
        }

        return null;
    }
}
