<?php

declare(strict_types=1);

namespace App\TransportProof;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Definition\Data\Operation;

final readonly class ClientScopes implements ClientScopeAuthorizer
{
    public function __construct(private NotesAuthority $authority) {}

    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $this->authority->clientAllows($operation, $context);
    }
}
