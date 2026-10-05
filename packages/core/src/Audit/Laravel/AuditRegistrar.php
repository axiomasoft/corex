<?php

declare(strict_types=1);

namespace CoreX\Audit\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Contracts\AuditRetention;
use CoreX\Audit\Contracts\SynchronousAuditWriter;
use CoreX\Audit\Internal\AuditObserver;
use CoreX\Audit\Internal\CurrentActor;
use CoreX\Audit\Internal\DatabaseSynchronousAuditWriter;
use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Laravel\ConfiguredImplementation;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Http\Request;
use Override;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AuditRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(AuditLogger::class, static fn (Application $app): AuditLogger => ConfiguredImplementation::resolve($app, AuditLogger::class, $app->make(CoreOptions::class)->auditLogger));
        $app->scoped(SynchronousAuditWriter::class, static fn (Application $app): SynchronousAuditWriter => new DatabaseSynchronousAuditWriter($app->make(ConnectionResolverInterface::class)));
        $app->scoped(CurrentActor::class, static fn (Application $app): CurrentActor => new CurrentActor(
            auth: $app->make(AuthFactory::class),
            impersonation: $app->make(ImpersonationService::class),
            request: static fn (): ?Request => $app->runningInConsole() || ! $app->bound('request') ? null : $app->make(Request::class),
        ));
        $app->scoped(AuditObserver::class);
        $app->singleton(AuditRetention::class, static fn (Application $app): AuditRetention => ConfiguredImplementation::resolve($app, AuditRetention::class, $app->make(CoreOptions::class)->auditRetention));
    }

    #[Override]
    public function boot(Application $app): void {}
}
