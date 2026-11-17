<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use CoreX\Attributes\Api;
use Packman\Context\Contracts\ContextScopeInterface;

/**
 * The current account/workspace pair a request or job runs under. Resolved
 * by axiomasoft/corex-tenancy; boxed installs get a constant singleton via
 * {@see SingleAccountResolver}.
 *
 * Contract: B-10 §3.1, B-11 §3.1 (owner: B-11).
 */
#[Api(since: '1.0')]
final readonly class TenantContext implements ContextScopeInterface
{
    public function __construct(
        public AccountRef $account,
        public ?WorkspaceRef $workspace = null,
        private bool $boxed = false,
    ) {}

    /**
     * True iff this context was produced by {@see SingleAccountResolver}
     * (boxed installs), not by a real P2 tenancy resolver.
     */
    public function id(): string
    {
        return $this->account->id;
    }

    public function isBoxed(): bool
    {
        return $this->boxed;
    }

    public function forWorkspace(WorkspaceRef $workspace): self
    {
        return new self(account: $this->account, workspace: $workspace, boxed: $this->boxed);
    }
}
