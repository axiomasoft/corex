<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\CentralContextException;

/**
 * Adapts the cloud {@see TenancyManager} to the P1.12
 * {@see TenantContextResolver} contract (B-11 §3.2): `current()` reads
 * whatever context `DatabaseTenancyManager::initialize()` last set, throwing
 * when called outside any initialized tenant.
 */
final readonly class TenancyManagerContextResolver implements TenantContextResolver
{
    public function __construct(
        private TenancyManager $manager,
    ) {}

    public function current(): TenantContext
    {
        return $this->manager->context() ?? throw new CentralContextException;
    }
}
