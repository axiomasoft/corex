<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\CentralContextException;
use Packman\Context\Contracts\ContextResolverInterface;

/** Adapts CoreX's account context without capturing request-local state. */
final readonly class PackmanContextResolver implements ContextResolverInterface
{
    public function __construct(private TenantContextResolver $resolver) {}

    public function current(): ?TenantContext
    {
        try {
            return $this->resolver->current();
        } catch (CentralContextException) {
            return null;
        }
    }
}
