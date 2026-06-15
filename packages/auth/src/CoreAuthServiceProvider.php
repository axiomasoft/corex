<?php

declare(strict_types=1);

namespace CoreX\Auth;

use AzGuard\Contracts\ContextGuard;
use CoreX\Auth\Tenancy\TenantContextGuard;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Override;

final class CoreAuthServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        // Provide AzGuard's optional ContextGuard binding (ADR-009): contextual
        // checks (hasPermissionIn) resolve entities via corex/core's registry.
        $this->app->singleton(ContextGuard::class, TenantContextGuard::class);
    }

    public function boot(): void
    {
        $this->registerBladeDirectives();
    }

    /**
     * Registers entity-scoped Blade directives layered on AzGuard's scoped
     * roles: "scope" checks a role scoped to an entity and "entity" checks a
     * permission scoped to an entity, e.g. scope(role, $project) or
     * entity('crm.deal.view', $deal), each closed by its end-directive.
     */
    private function registerBladeDirectives(): void
    {
        Blade::directive('scope', static fn (string $expression): string => "<?php if (auth()->check() && auth()->user()->hasScopedRole({$expression})): ?>");
        Blade::directive('endscope', static fn (): string => '<?php endif; ?>');

        Blade::directive('entity', static fn (string $expression): string => "<?php if (auth()->check() && auth()->user()->hasScopedPermission({$expression})): ?>");
        Blade::directive('endentity', static fn (): string => '<?php endif; ?>');
    }
}
