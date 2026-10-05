<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard;

use AzGuard\Contracts\AzGuardManagerInterface;
use CoreX\Access\Matrix\Events\RoleMatrixCompiled;
use CoreX\AccessAzguard\Internal\AutMatrixGrantSource;
use CoreX\AccessAzguard\Internal\Laravel\AccessAzguardBindingsRegistrar;
use CoreX\AccessAzguard\Internal\MatrixPermissionCatalogBuilder;
use CoreX\AccessAzguard\Internal\SyncAzGuardRolePermissions;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\View\Compilers\BladeCompiler;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccessAzguardRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        (new AccessAzguardBindingsRegistrar)->register($app);
    }

    #[Override]
    public function boot(Application $app): void
    {
        $manager = $app->make(AzGuardManagerInterface::class);
        $manager->registerGrantSource(AutMatrixGrantSource::class);
        $manager->registerCatalogBuilder(MatrixPermissionCatalogBuilder::class);

        $app->make(Dispatcher::class)->listen(RoleMatrixCompiled::class, SyncAzGuardRolePermissions::class);

        $blade = $app->make(BladeCompiler::class);
        $blade->directive('scope', static fn (string $expression): string => "<?php if (auth()->check() && auth()->user()->hasScopedRole({$expression})): ?>");
        $blade->directive('endscope', static fn (): string => '<?php endif; ?>');
        $blade->directive('entity', static fn (string $expression): string => "<?php if (auth()->check() && auth()->user()->hasScopedPermission({$expression})): ?>");
        $blade->directive('endentity', static fn (): string => '<?php endif; ?>');
    }
}
