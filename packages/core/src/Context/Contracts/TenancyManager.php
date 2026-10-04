<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;

/**
 * Single point of tenant-context switching (connection/cache/session/queue).
 * Boxed installs bind a null-manager (constant context); cloud installs bind
 * the implementation of `axiomasoft/corex-tenancy` — the choice is a container
 * binding, never an `if(cloud)` branch.
 *
 * Switching semantics of the cloud implementation (PLAN3.D95):
 * - `initialize()` of another account ends the current one first; the same account is a no-op;
 * - every switch — `initialize()`, `end()`, leaving `runFor()` — forgets the container's scoped
 *   instances, so a scoped service resolved inside a context holds that context's connection and
 *   one resolved before a switch must not be kept across it;
 * - `runFor()` restores the previous account and workspace in `finally`, also after a failure;
 * - a failed restoration poisons the runtime: it refuses every further call until the
 *   application is recreated.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TenancyManager
{
    /**
     * The active context, or null when running in the central/root scope
     * (not inside any tenant).
     */
    public function context(): ?TenantContext;

    public function initialized(): bool;

    public function initialize(AccountRef $account): void;

    /**
     * Narrows (or clears, with null) the workspace within the already-
     * initialized account context. No-op on the boxed null-manager.
     */
    public function setWorkspace(?WorkspaceRef $workspace): void;

    public function end(): void;

    /**
     * Runs `$callback` inside the given account/workspace context, then
     * unconditionally reverts (finally) — the seam for control-plane sweeps
     * and cron jobs run outside a request cycle.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed;
}
