<?php

declare(strict_types=1);

namespace CoreX\Access\Laravel;

use CoreX\Access\Access\Internal\EffectiveWorkspaceScope;
use CoreX\Access\Access\Internal\WorkspaceScopeWiring;
use CoreX\Access\Extend\Internal\PermissionsMerger;
use CoreX\Access\Identity\Laravel\PrincipalUserProvider;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use InvalidArgumentException;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Events\Lifecycle\ModuleDisabled;
use Packman\Modules\Events\Lifecycle\ModuleEnabled;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccessIntegrationRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void {}

    #[Override]
    public function boot(Application $app): void
    {
        $this->registerIdentityProvider($app);

        WorkspaceScopeWiring::wire(
            registry: $app->make(EntityRegistry::class),
            scopeClass: $app->make(EffectiveWorkspaceScope::class)->class,
            make: static fn (string $class): Scope => self::workspaceScope(app: $app, class: $class),
        );

        $app->make(Dispatcher::class)->listen(ModuleEnabled::class, PermissionsMerger::class);
        $app->make(Dispatcher::class)->listen(ModuleDisabled::class, PermissionsMerger::class);
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
     * Registers the `corex-principal` Laravel auth driver — never
     * touches `config('auth.*')` itself (a package does not impose the
     * consuming app's guard wiring); a consumer opts in with
     * `auth.providers.users.driver = 'corex-principal'` (see README).
     */
    private function registerIdentityProvider(Application $app): void
    {
        if (! $app->make(AuthOptions::class)->identityEnabled) {
            return;
        }

        $app->make(AuthManager::class)->provider('corex-principal', static fn (): PrincipalUserProvider => new PrincipalUserProvider(
            options: $app->make(AuthOptions::class),
        ));
    }
}
