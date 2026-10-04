<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use Closure;
use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
use CoreX\Tenancy\Runtime\Events\TenantContextEnded;
use CoreX\Tenancy\Runtime\Events\TenantContextInitialized;
use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Override;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Own DB-per-account engine. Every switch forgets scoped instances, so a scoped service
 * resolved inside a context always holds that context's connection (PLAN3.D95 п.3).
 */
final class DatabaseTenancyManager implements TenancyManager
{
    private ?AccountRef $account = null;

    private ?WorkspaceRef $workspace = null;

    /** @var list<TenantBootstrapper> Includes a bootstrapper BEFORE calling bootstrap(). */
    private array $started = [];

    private bool $poisoned = false;

    /** @param list<TenantBootstrapper> $bootstrappers fixed order from TenancyOptions */
    public function __construct(
        private readonly array $bootstrappers,
        private readonly AccountRead $accounts,
        private readonly AccountConnectionResolver $connections,
        private readonly Container $container,
        private readonly Dispatcher $events,
        private readonly string $centralConnection,
        private readonly LoggerInterface $logger,
    ) {}

    #[Override]
    public function context(): ?TenantContext
    {
        $this->assertHealthy();

        return $this->account === null ? null : new TenantContext($this->account, $this->workspace);
    }

    #[Override]
    public function initialized(): bool
    {
        $this->assertHealthy();

        return $this->account !== null;
    }

    #[Override]
    public function initialize(AccountRef $account): void
    {
        $this->assertHealthy();

        if ($this->account?->id === $account->id) {
            return;
        }

        $this->end();

        try {
            $current = $this->accounts->find($account->id, $this->centralConnection)
                ?? throw TenantContextUnavailable::missingAccount($account->id);
            $database = $this->connections->resolve($current);
            $context = new TenantContext($current);

            foreach ($this->bootstrappers as $bootstrapper) {
                $this->started[] = $bootstrapper;
                $bootstrapper->bootstrap($context, $database);
            }

            $this->account = $current;
            $this->workspace = null;
            $this->resetScope();
            $this->events->dispatch(new TenantContextInitialized($current->id));
        } catch (Throwable $failure) {
            $this->account = null;
            $this->workspace = null;

            try {
                $this->revertAll(array_reverse($this->started));
            } catch (Throwable $cleanup) {
                $this->reportCleanupFailure($failure, $cleanup);
            } finally {
                $this->started = [];
                $this->resetScope();
            }

            throw $failure;
        }
    }

    #[Override]
    public function setWorkspace(?WorkspaceRef $workspace): void
    {
        $this->assertHealthy();

        if ($workspace !== null && $this->account === null) {
            throw TenantContextUnavailable::notInitialized();
        }

        $this->workspace = $workspace;
    }

    #[Override]
    public function end(): void
    {
        $this->assertHealthy();

        $accountId = $this->account?->id;
        $this->account = null;
        $this->workspace = null;

        try {
            $this->revertAll(array_reverse($this->started));
        } finally {
            $this->started = [];
            $this->resetScope();
        }

        if ($accountId !== null) {
            $this->events->dispatch(new TenantContextEnded($accountId));
        }
    }

    #[Override]
    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        $previousAccount = $this->account;
        $previousWorkspace = $this->workspace;
        $original = null;

        try {
            $this->initialize($account);
            $this->setWorkspace($workspace);

            return $callback();
        } catch (Throwable $failure) {
            $original = $failure;

            throw $failure;
        } finally {
            // A failed revert poisons the runtime; restoration must not mask its causes.
            if (! $this->poisoned) {
                try {
                    if ($previousAccount === null) {
                        $this->end();
                    } else {
                        $this->initialize($previousAccount);
                        $this->setWorkspace($previousWorkspace);
                    }
                } catch (Throwable $cleanup) {
                    if ($original !== null) {
                        $this->reportCleanupFailure($original, $cleanup);
                    }

                    throw $cleanup;
                }
            }
        }
    }

    private function reportCleanupFailure(Throwable $original, Throwable $cleanup): never
    {
        try {
            $this->logger->error('Tenant runtime cleanup failed', [
                'exception' => $cleanup,
                'original_exception' => $original,
            ]);
        } catch (Throwable) {
            // A failing log transport must not replace either runtime cause.
        }

        throw new TenantCleanupFailure($original, $cleanup);
    }

    /** @param list<TenantBootstrapper> $bootstrappers already in revert order */
    private function revertAll(array $bootstrappers): void
    {
        $first = null;

        foreach ($bootstrappers as $bootstrapper) {
            try {
                $bootstrapper->revert();
            } catch (Throwable $failure) {
                $first ??= $failure;
            }
        }

        if ($first !== null) {
            $this->poisoned = true;

            throw $first;
        }
    }

    private function resetScope(): void
    {
        $this->container->forgetScopedInstances();
    }

    private function assertHealthy(): void
    {
        if ($this->poisoned) {
            throw TenantContextUnavailable::runtimePoisoned();
        }
    }
}
