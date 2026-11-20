<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use Closure;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface WorkPrincipalAuthority
{
    public function current(TenantContext $context): ScopePrincipal;

    /**
     * Revalidate live membership and service grants, install the principal and restore the outer frame.
     *
     * @template T
     *
     * @param Closure():T $work
     * @return T */
    public function run(DataScope $scope, TenantContext $context, bool $durable, Closure $work): mixed;
}
