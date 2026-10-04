<?php

declare(strict_types=1);

namespace CoreX\Access\Laravel;

use AzGuard\Contracts\AzGuardManagerInterface;
use CoreX\Access\Access\Internal\AutMatrixGrantSource;
use CoreX\Access\Access\Internal\WorkspaceScopeWiring;
use CoreX\Access\Extend\Internal\PermissionsMerger;
use CoreX\Access\Identity\Laravel\PrincipalUserProvider;
use CoreX\Access\Matrix\Internal\MatrixPermissionCatalogBuilder;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Events\ModuleDisabled;
use CoreX\Modules\Events\ModuleEnabled;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccessIntegrationRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void {}

    #[Override]
    public function boot(Application $app): void
    {
        $this->registerBladeDirectives();
        $this->registerIdentityProvider($app);

        $manager = $app->make(AzGuardManagerInterface::class);
        $manager->registerGrantSource(AutMatrixGrantSource::class);
        $manager->registerCatalogBuilder(MatrixPermissionCatalogBuilder::class);

        WorkspaceScopeWiring::wire(
            $app->make(EntityRegistry::class),
            $app->make(AuthOptions::class)->workspaceScopeClass,
            static fn (string $class): Scope => self::workspaceScope($app, $class),
        );

        Event::listen(ModuleEnabled::class, PermissionsMerger::class);
        Event::listen(ModuleDisabled::class, PermissionsMerger::class);
    }

    /** @return Scope<Model> */
    private static function workspaceScope(Application $app, string $class): Scope
    {
        $scope = $app->make($class);

        if (! $scope instanceof Scope) {
            throw new InvalidArgumentException("Workspace scope class [{$class}] must implement ".Scope::class.'.');
        }

        return $scope;
    }

    /**
     * Registers the `corex-principal` Laravel auth driver (D125) — never
     * touches `config('auth.*')` itself (a package does not impose the
     * consuming app's guard wiring); a consumer opts in with
     * `auth.providers.users.driver = 'corex-principal'` (see README).
     */
    private function registerIdentityProvider(Application $app): void
    {
        if (! $app->make(AuthOptions::class)->identityEnabled) {
            return;
        }

        Auth::provider('corex-principal', static fn (): PrincipalUserProvider => new PrincipalUserProvider(
            options: $app->make(AuthOptions::class),
        ));
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
