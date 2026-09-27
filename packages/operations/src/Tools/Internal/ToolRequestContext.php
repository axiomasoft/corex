<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\Contracts\Principal;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\ToolRequestContext as ToolRequestContextContract;
use CoreX\Api\InvocationMetadata;
use CoreX\Tenancy\TenantContext;
use Override;

/** @internal Additive wrapper exposing validated tool metadata to handlers. */
final readonly class ToolRequestContext implements ToolRequestContextContract
{
    public function __construct(
        private RequestContext $context,
        private ?string $operationKey,
        private string $toolContractHash,
    ) {}

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
        return $this->context->metadata();
    }

    #[Override]
    public function operationKey(): ?string
    {
        return $this->operationKey;
    }

    #[Override]
    public function toolContractHash(): string
    {
        return $this->toolContractHash;
    }
}
