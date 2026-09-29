<?php

declare(strict_types=1);

namespace CoreX\Access;

use AzGuard\Contracts\AzGuardManagerInterface;
use AzGuard\Contracts\ContextGuard;
use AzGuard\Contracts\PermissionLayer;
use CoreX\Access\Access\Internal\AutMatrixGrantSource;
use CoreX\Access\Access\Internal\DefaultAccessChecker;
use CoreX\Access\Access\Internal\DefaultScopeResolver;
use CoreX\Access\Access\Internal\ScopeAvailability;
use CoreX\Access\Access\Internal\WorkspacePermissionLayer;
use CoreX\Access\Access\Internal\WorkspaceScopeWiring;
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Contracts\RoleMatrixCompiler;
use CoreX\Access\Contracts\ScopeResolver;
use CoreX\Access\Extend\Internal\PermissionsMerger;
use CoreX\Access\Identity\Internal\PrincipalUserProvider;
use CoreX\Access\Internal\Support\AccessConfigSection;
use CoreX\Access\Internal\Support\AuthOptions;
use CoreX\Access\Matrix\Internal\DatabaseRoleMatrixCompiler;
use CoreX\Access\Matrix\Internal\MatrixPermissionCatalogBuilder;
use CoreX\Access\Tenancy\Internal\TenantContextGuard;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Events\ModuleDisabled;
use CoreX\Modules\Events\ModuleEnabled;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Override;

final class CoreAuthServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([AccessConfigSection::class], ConfigReportSection::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-access.php', 'corex-access');

        $raw = $this->app['config']->get('corex-access', []);
        $options = AuthOptions::fromArray(is_array($raw) ? $raw : []);
        $this->app->instance(AuthOptions::class, $options);

        // Provide AzGuard's optional ContextGuard binding (ADR-009): contextual
        // checks (hasPermissionIn) resolve entities via axiomasoft/corex's registry.
        $this->app->singleton(ContextGuard::class, TenantContextGuard::class);

        // scoped(), not singleton() — DefaultAccessChecker's scopeFor() memo
        // must not survive across requests on a reused worker (Octane).
        $this->app->scoped(AccessChecker::class, DefaultAccessChecker::class);

        // scoped() for the same reason: ScopeAvailability's per-(entity_key,
        // scope) memo (P2.9 D114) must not survive a reused Octane worker
        // either, and DefaultScopeResolver is resolved once per listing
        // query — binding it keeps that one ScopeAvailability instance (and
        // its memo) shared across every apply() call within one request.
        $this->app->scoped(ScopeAvailability::class);
        $this->app->scoped(ScopeResolver::class, DefaultScopeResolver::class);

        // P2.21: PermissionsMerger's lifecycle listener needs this resolved
        // through the interface — nothing bound it before (P2.8/P2.9 always
        // constructed DatabaseRoleMatrixCompiler directly).
        $this->app->bind(RoleMatrixCompiler::class, DatabaseRoleMatrixCompiler::class);

        // The PermissionLayer slot is singular (findings/P2.8-azguard-
        // inventory-2026-07-26.md §6) — the config switch (D101 risk note)
        // lets a target build step aside for another package's layer instead
        // of conflicting over it.
        if ($options->bindPermissionLayer) {
            $this->app->bind(PermissionLayer::class, WorkspacePermissionLayer::class);
        }

        $this->applyAzGuardPins($options);
    }

    public function boot(): void
    {
        $this->registerBladeDirectives();
        $this->registerPublishing();
        $this->registerIdentityProvider();

        $manager = $this->app->make(AzGuardManagerInterface::class);
        $manager->registerGrantSource(AutMatrixGrantSource::class);
        $manager->registerCatalogBuilder(MatrixPermissionCatalogBuilder::class);

        WorkspaceScopeWiring::wire(
            $this->app->make(EntityRegistry::class),
            $this->app->make(AuthOptions::class)->workspaceScopeClass,
        );

        Event::listen(ModuleEnabled::class, PermissionsMerger::class);
        Event::listen(ModuleDisabled::class, PermissionsMerger::class);
    }

    /**
     * axiomasoft/corex-access does not fork AzGuard (D15): the config pins that make
     * AzGuard line up with DB_SCHEMA §3.5 / CoreX's uuid principals (D104)
     * are applied onto AzGuard's own config here, sourced from OUR
     * publishable corex-access config — never a second copy of the values.
     * AzGuard's migrations read this config at `migrate` time (findings/
     * P2.8-azguard-inventory-2026-07-26.md §2), so setting it in register()
     * — well before any migrate call — is early enough regardless of
     * provider registration order.
     */
    private function applyAzGuardPins(AuthOptions $options): void
    {
        $config = $this->app['config'];
        $pins = $options->azguardPins;
        $tableNames = is_array($pins['table_names'] ?? null) ? $pins['table_names'] : [];
        $columnNames = is_array($pins['column_names'] ?? null) ? $pins['column_names'] : [];
        $models = is_array($pins['models'] ?? null) ? $pins['models'] : [];

        $config->set('az-guard.table_names.roles', $tableNames['roles'] ?? 'az_guard_roles');
        $config->set('az-guard.column_names.morph_type', $columnNames['morph_type'] ?? 'uuid');
        $config->set('az-guard.models.role', $models['role'] ?? null);
    }

    /**
     * Registers the `corex-principal` Laravel auth driver (D125) — never
     * touches `config('auth.*')` itself (a package does not impose the
     * consuming app's guard wiring); a consumer opts in with
     * `auth.providers.users.driver = 'corex-principal'` (see README).
     */
    private function registerIdentityProvider(): void
    {
        if (! $this->app->make(AuthOptions::class)->identityEnabled) {
            return;
        }

        Auth::provider('corex-principal', fn (): PrincipalUserProvider => new PrincipalUserProvider(
            options: $this->app->make(AuthOptions::class),
        ));
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/corex-access.php' => config_path('corex-access.php'),
        ], 'corex-access-config');

        // Publish-only, mirroring axiomasoft/corex-tenancy's own tenant-DB migrations
        // (D33/A19/A62 precedent): a golden-template build assembles these
        // deliberately, never an ad hoc migrate on a live tenant connection.
        $this->publishes([
            __DIR__.'/../database/migrations/tenant' => database_path('migrations/tenant'),
        ], 'corex-access-migrations-tenant');

        // Cloud profile only (D102) — boxed installs never publish this tag,
        // wsp_workspaces/users belong to axiomasoft/corex-tenancy, absent there (AC-28).
        $this->publishes([
            __DIR__.'/../database/migrations/tenant-cloud' => database_path('migrations/tenant-cloud'),
        ], 'corex-access-migrations-tenant-cloud');
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
