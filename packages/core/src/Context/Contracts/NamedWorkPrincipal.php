<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use Closure;
use CoreX\Context\Data\TenantContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface NamedWorkPrincipal
{
    /**
     * Admit an explicit, live host grant for the service in this context.
     * Install it in the host's WorkPrincipalAuthority and restore the outer principal on exit.
     *
     * @template T
     *
     * @param  Closure():T  $work
     * @return T
     */
    public function run(TenantContext $context, string $serviceId, Closure $work): mixed;
}
