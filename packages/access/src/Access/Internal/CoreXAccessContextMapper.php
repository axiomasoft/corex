<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\Kernel\Identity\AccessScope;
use AzGuard\Kernel\Identity\AssignmentScopeRef;
use AzGuard\Kernel\Identity\SubjectRef;
use AzGuard\Kernel\Identity\TenantRef;
use CoreX\Access\Data\RecordContext;
use CoreX\Context\Contracts\Principal;
use CoreX\Context\Contracts\TenancyManager;
use Packman\Attributes\Internal;

/** @internal Maps trusted CoreX identity, without reading authorization tables. */
#[Internal]
final readonly class CoreXAccessContextMapper
{
    public function __construct(private TenancyManager $tenancy) {}

    public function subject(Principal $principal): SubjectRef
    {
        return SubjectRef::of($principal->principalType()->value, $principal->principalId());
    }

    public function scope(?RecordContext $context = null): ?AccessScope
    {
        $current = $this->tenancy->context();

        if ($current === null) {
            return null;
        }

        $tenant = $current->isBoxed() ? TenantRef::global() : TenantRef::of('account', $current->account->id);
        $assignment = match (true) {
            $context !== null => AssignmentScopeRef::of($context->type, $context->id),
            $current->workspace !== null => AssignmentScopeRef::of('workspace', $current->workspace->id),
            default => AssignmentScopeRef::global(),
        };

        return AccessScope::in($tenant, $assignment);
    }
}
