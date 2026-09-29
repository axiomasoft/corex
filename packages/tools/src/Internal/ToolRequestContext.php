<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Context\Data\TenantContext;
use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\InvocationMetadata;
use Override;

/** @internal Additive wrapper exposing validated tool metadata to handlers. */
final readonly class ToolRequestContext implements \CoreX\Tools\Contracts\ToolRequestContext
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
