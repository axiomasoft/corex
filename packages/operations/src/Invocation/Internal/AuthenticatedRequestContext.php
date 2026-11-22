<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Context\Contracts\Principal;
use CoreX\Context\Data\TenantContext;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use Override;
use Packman\Attributes\Internal;

#[Internal]
final readonly class AuthenticatedRequestContext implements ClientRequestContext
{
    public function __construct(private RequestContext $context, private string $client, private ?InvocationMetadata $invocation = null) {}

    #[Override]
    public function clientId(): string
    {
        return $this->client;
    }

    #[Override]
    public function tenant(): TenantContext
    {
        return $this->context->tenant();
    }

    #[Override]
    public function principal(): Principal
    {
        return $this->context->principal();
    }

    #[Override]
    public function metadata(): InvocationMetadata
    {
        return $this->invocation ?? $this->context->metadata();
    }
}
