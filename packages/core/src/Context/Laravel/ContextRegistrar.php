<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\CentralWorkRunner;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ImpersonationGuard;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Contracts\ScopeAuthority;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Internal\AuthenticatedWorkPrincipal;
use CoreX\Context\Internal\BoxedCentralWorkRunner;
use CoreX\Context\Internal\BoxedWorkScopeDirectory;
use CoreX\Context\Internal\ImpersonationGuard as ImpersonationGuardImplementation;
use CoreX\Context\Internal\LocalWorkTransactions;
use CoreX\Context\Internal\NullTenancyManager;
use CoreX\Context\Internal\RequiredPhysicalBindingVerifier;
use CoreX\Context\Internal\StoredWorkOrigins;
use CoreX\Context\Internal\VerifiedWorkScope;
use CoreX\Context\Internal\WorkProcessHealth;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Queue;
use Illuminate\Queue\QueueManager;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Laravel\ConfiguredImplementation;
use Packman\Foundation\Contracts\PackageRegistrar;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ContextRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->afterResolving('queue', static fn (QueueManager $queues) => $queues->addConnector('sync', static fn (): ScopedSyncConnector => new ScopedSyncConnector));
        $app->make(Dispatcher::class)->listen(JobProcessing::class, [WorkQueueBoundary::class, 'processing']);
        $app->bind(ImpersonationGuard::class, ImpersonationGuardImplementation::class);
        $app->singleton(TenantContextResolver::class, static fn (Application $app): TenantContextResolver => ConfiguredImplementation::resolve(app: $app, contract: TenantContextResolver::class, implementation: $app->make(CoreOptions::class)->tenantContextResolver));
        $app->singleton(ScopeOptions::class, static fn (Application $app): ScopeOptions => ScopeOptionsFactory::fromArray(
            (array) $app->make(Repository::class)->get('corex.scope', []),
        ));
        $app->singleton(WorkProcessHealth::class);
        $app->singleton(LocalWorkTransactions::class);
        $app->singleton(WorkQueueHandlers::class);
        $app->singleton(WorkQueueBoundary::class, static fn (Application $app): WorkQueueBoundary => new WorkQueueBoundary(handlers: $app->make(WorkQueueHandlers::class), application: static fn (): Container => Container::getInstance()));
        $app->bind(CentralWorkRunner::class, BoxedCentralWorkRunner::class);
        $app->scoped(VerifiedWorkScope::class, static fn (): VerifiedWorkScope => new VerifiedWorkScope(static fn (): Container => Container::getInstance()));
        $app->alias(VerifiedWorkScope::class, CurrentWorkScope::class);
        $app->alias(VerifiedWorkScope::class, ProviderEffectRecovery::class);
        $app->alias(VerifiedWorkScope::class, DataScopeFactory::class);
        $app->alias(VerifiedWorkScope::class, WorkScopeRunner::class);
        $app->alias(VerifiedWorkScope::class, StoredWorkRunner::class);
        $app->scoped(StoredWorkIssuer::class, StoredWorkOrigins::class);
        $app->scoped(ScopeAuthority::class, DatabaseScopeControl::class);
        $app->scoped(ScopeAdmission::class, DatabaseScopeControl::class);
        $app->scoped(ProviderEffectLedger::class, DatabaseScopeControl::class);
        $app->scoped(PhysicalBindingVerifier::class, RequiredPhysicalBindingVerifier::class);
        $app->scoped(WorkScopeDirectory::class, BoxedWorkScopeDirectory::class);
        $app->scoped(WorkPrincipalAuthority::class, AuthenticatedWorkPrincipal::class);
        $app->bind(NullTenancyManager::class, static fn (): NullTenancyManager => new NullTenancyManager(static fn (): TenantContextResolver => Container::getInstance()->make(TenantContextResolver::class)));
        $app->singleton(TenancyManager::class, static fn (Application $app): TenancyManager => ConfiguredImplementation::resolve(app: $app, contract: TenancyManager::class, implementation: $app->make(CoreOptions::class)->tenancyManager));
        $app->singleton(ImpersonationService::class, static fn (Application $app): ImpersonationService => ConfiguredImplementation::resolve(app: $app, contract: ImpersonationService::class, implementation: $app->make(CoreOptions::class)->impersonationService));
    }

    #[Override]
    public function boot(Application $app): void
    {
        Queue::createPayloadUsing(self::payloadHook(...));
        $events = $app->make(Dispatcher::class);

        // A transport failure skips Octane's OperationTerminated. Clear the root that its next sandbox will clone.
        $worker = $app->make(Container::class);
        $events->listen('Laravel\\Octane\\Events\\WorkerErrorOccurred', $worker->forgetScopedInstances(...));
        $events->listen(QueryExecuted::class, [LocalWorkTransactions::class, 'query']);
        $events->listen([TransactionCommitted::class, TransactionRolledBack::class], [LocalWorkTransactions::class, 'completed']);

        $events->listen(JobAttempted::class, [WorkQueueBoundary::class, 'finished']);
    }

    /** @param array<string,mixed> $payload
     * @return array<string,mixed> */
    private static function payloadHook(string $connection, ?string $queue, array $payload): array
    {
        $container = Container::getInstance();

        if (! $container->bound(WorkQueueBoundary::class) || ($payload['job'] ?? null) === WorkQueueHandler::class.'@fire') {
            return [];
        }

        return $container->make(WorkQueueBoundary::class)->stamp($payload);
    }
}
