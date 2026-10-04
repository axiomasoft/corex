<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\TenantContext;

/**
 * Switches one runtime facility (connection, cache, session, …) to an account.
 *
 * Implementations must not depend — directly or transitively — on
 * `TenancyManager` or `TenantContextResolver`, and must not start a nested
 * context switch from inside `bootstrap()`/`revert()`: the manager constructs
 * the whole list eagerly and calls it while the switch is in flight.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TenantBootstrapper
{
    /** Switch one runtime facility to the account; must be revertible by {@see revert()}. */
    public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void;

    /** Restore captured state even after a partial or throwing {@see bootstrap()}; idempotent. */
    public function revert(): void;
}
