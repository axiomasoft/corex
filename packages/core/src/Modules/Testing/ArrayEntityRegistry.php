<?php

declare(strict_types=1);

namespace CoreX\Modules\Testing;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Data\EntityDefinition;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Internal\InMemoryEntityRegistry;

/**
 * In-memory EntityRegistry. Module providers register() their entities at
 * boot; the compiled hot-path registry replaces this for production reads
 * once RegistryCompiler exists.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ArrayEntityRegistry implements EntityRegistry
{
    private InMemoryEntityRegistry $registry;

    /**
     * @param  Closure(string, TenantContext):bool|null  $moduleActive  Real active-module
     *                                                                  predicate (ModuleRegistry::isActive).
     *                                                                  Takes both the module composer name AND the
     *                                                                  tenant ctx — activity is per-account.
     * @param  Closure(TenantContext):list<string>|null  $activeModules  One active-module snapshot per list operation.
     */
    public function __construct(?Closure $moduleActive = null, private ?Closure $activeModules = null)
    {
        $this->registry = new InMemoryEntityRegistry(moduleActive: $moduleActive, activeModules: $activeModules);
    }

    public function register(EntityDefinition $definition): void
    {
        $this->registry->register($definition);
    }

    public function freeze(): void
    {
        $this->registry->freeze();
    }

    public function get(string $handle): EntityDefinition
    {
        return $this->registry->get($handle);
    }

    public function has(string $handle): bool
    {
        return $this->registry->has($handle);
    }

    public function all(?TenantContext $ctx = null): array
    {
        return $this->registry->all($ctx);
    }

    public function byCapability(Capability $cap, ?TenantContext $ctx = null): array
    {
        return $this->registry->byCapability(cap: $cap, ctx: $ctx);
    }

    public function forModel(string $modelClass): ?EntityDefinition
    {
        return $this->registry->forModel($modelClass);
    }
}
