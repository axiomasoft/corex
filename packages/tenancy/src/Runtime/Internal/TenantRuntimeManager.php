<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper;
use CoreX\Tenancy\Runtime\Events\TenantContextEnded;
use CoreX\Tenancy\Runtime\Events\TenantContextInitialized;
use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Tenant\Contracts\Runtime\TenantRuntime;
use Tenant\Data\Context\Purpose;
use Tenant\Data\Context\Selection;
use Tenant\Data\Runtime\FrameToken;
use Throwable;

/** @internal Platform projection only: Tenant owns transition, restoration and poison semantics. */
final class TenantRuntimeManager implements TenancyManager
{
    private ?FrameToken $frame = null;

    public function __construct(
        private readonly TenantRuntime $runtime,
        private readonly AccountIsolationMapper $mapper,
        private readonly Container $container,
        private readonly Dispatcher $events,
    ) {}

    public function context(): ?TenantContext
    {
        $state = $this->runtime->current();

        return $state->kind === 'active' ? $this->mapper->context($state) : null;
    }

    public function initialized(): bool
    {
        return $this->context() !== null;
    }

    public function initialize(AccountRef $account): void
    {
        if ($this->context()?->account->id === $account->id) {
            return;
        }

        $selection = $this->mapper->selection($account);
        $this->end();
        $this->enter($account, null, $selection);
    }

    public function setWorkspace(?WorkspaceRef $workspace): void
    {
        $current = $this->context();

        if ($current === null) {
            if ($workspace !== null) {
                throw TenantContextUnavailable::notInitialized();
            }

            return;
        }

        if ($current->workspace?->id === $workspace?->id) {
            return;
        }

        $selection = $this->mapper->selection($current->account, $workspace);
        $this->end();
        $this->enter($current->account, $workspace, $selection);
    }

    public function end(): void
    {
        // Only restore frames opened by this adapter; a surrounding Tenant middleware owns its frame.
        if ($this->frame === null) {
            $this->runtime->current();

            return;
        }

        $frame = $this->frame;
        $account = null;

        try {
            $account = $this->context()?->account->id;
        } finally {
            try {
                $this->runtime->restore($frame);
                $this->frame = null;
            } finally {
                $this->container->forgetScopedInstances();
            }
        }

        if ($account !== null) {
            $this->events->dispatch(new TenantContextEnded($account));
        }
    }

    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        $selection = $this->mapper->selection($account, $workspace);
        $previous = $this->frame;
        $this->frame = null;

        try {
            return $this->runtime->run($selection, new Purpose('corex:account'), function () use ($account, $workspace, $callback): mixed {
                $this->container->forgetScopedInstances();
                $this->assertProjection($account, $workspace);

                try {
                    return $callback();
                } finally {
                    // Close only frames explicitly opened via this adapter inside the callback.
                    // TenantRuntime::run owns its frame, leakage detection and poison boundary.
                    $this->end();
                }
            });
        } finally {
            $this->frame = $previous;
            $this->container->forgetScopedInstances();
        }
    }

    private function enter(AccountRef $account, ?WorkspaceRef $workspace, ?Selection $selection = null): void
    {
        $selection ??= $this->mapper->selection($account, $workspace);
        $this->frame = $this->runtime->enter($selection, new Purpose('corex:account'));

        try {
            $this->container->forgetScopedInstances();
            $this->assertProjection($account, $workspace);
            $this->events->dispatch(new TenantContextInitialized($account->id));
        } catch (Throwable $failure) {
            $this->end();

            throw $failure;
        }
    }

    private function assertProjection(AccountRef $account, ?WorkspaceRef $workspace): void
    {
        $current = $this->context();

        if ($current === null || $current->account->id !== $account->id || $current->workspace?->id !== $workspace?->id) {
            throw TenantContextUnavailable::notInitialized();
        }
    }
}
