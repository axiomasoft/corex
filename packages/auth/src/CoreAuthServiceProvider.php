<?php

declare(strict_types=1);

namespace CoreX\Auth;

use AzGuard\Contracts\AzGuardManagerInterface;
use AzGuard\Contracts\ContextGuard;
use AzGuard\Contracts\PermissionLayer;
use CoreX\Auth\Access\AutMatrixGrantSource;
use CoreX\Auth\Access\DefaultAccessChecker;
use CoreX\Auth\Access\WorkspacePermissionLayer;
use CoreX\Auth\Contracts\AccessChecker;
use CoreX\Auth\Matrix\MatrixPermissionCatalogBuilder;
use CoreX\Auth\Tenancy\TenantContextGuard;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Override;

final class CoreAuthServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-auth.php', 'corex-auth');

        // Provide AzGuard's optional ContextGuard binding (ADR-009): contextual
        // checks (hasPermissionIn) resolve entities via corex/core's registry.
        $this->app->singleton(ContextGuard::class, TenantContextGuard::class);

        // scoped(), not singleton() — DefaultAccessChecker's scopeFor() memo
        // must not survive across requests on a reused worker (Octane).
        $this->app->scoped(AccessChecker::class, DefaultAccessChecker::class);

        // The PermissionLayer slot is singular (findings/P2.8-azguard-
        // inventory-2026-07-26.md §6) — the config switch (D101 risk note)
        // lets a target build step aside for another package's layer instead
        // of conflicting over it.
        if ((bool) config('corex-auth.bind_permission_layer', true)) {
            $this->app->bind(PermissionLayer::class, WorkspacePermissionLayer::class);
        }

        $this->applyAzGuardPins();
    }

    public function boot(): void
    {
        $this->registerBladeDirectives();
        $this->registerPublishing();

        $manager = $this->app->make(AzGuardManagerInterface::class);
        $manager->registerGrantSource(AutMatrixGrantSource::class);
        $manager->registerCatalogBuilder(MatrixPermissionCatalogBuilder::class);
    }

    /**
     * corex/auth does not fork AzGuard (D15): the config pins that make
     * AzGuard line up with DB_SCHEMA §3.5 / CoreX's uuid principals (D104)
     * are applied onto AzGuard's own config here, sourced from OUR
     * publishable corex-auth config — never a second copy of the values.
     * AzGuard's migrations read this config at `migrate` time (findings/
     * P2.8-azguard-inventory-2026-07-26.md §2), so setting it in register()
     * — well before any migrate call — is early enough regardless of
     * provider registration order.
     */
    private function applyAzGuardPins(): void
    {
        $config = $this->app['config'];

        $config->set('az-guard.table_names.roles', config('corex-auth.azguard_pins.table_names.roles'));
        $config->set('az-guard.column_names.morph_type', config('corex-auth.azguard_pins.column_names.morph_type'));
        $config->set('az-guard.models.role', config('corex-auth.azguard_pins.models.role'));
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/corex-auth.php' => config_path('corex-auth.php'),
        ], 'corex-auth-config');

        // Publish-only, mirroring corex/tenancy's own tenant-DB migrations
        // (D33/A19/A62 precedent): a golden-template build assembles these
        // deliberately, never an ad hoc migrate on a live tenant connection.
        $this->publishes([
            __DIR__.'/../database/migrations/tenant' => database_path('migrations/tenant'),
        ], 'corex-auth-migrations-tenant');

        // Cloud profile only (D102) — boxed installs never publish this tag,
        // wsp_workspaces/users belong to corex/tenancy, absent there (AC-28).
        $this->publishes([
            __DIR__.'/../database/migrations/tenant-cloud' => database_path('migrations/tenant-cloud'),
        ], 'corex-auth-migrations-tenant-cloud');
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
