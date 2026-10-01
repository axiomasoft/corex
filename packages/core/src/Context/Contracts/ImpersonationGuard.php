<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Enums\ImpersonationRestrictedAction;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface ImpersonationGuard
{
    public function assertAllowed(ImpersonationRestrictedAction $action): void;

    public function denies(ImpersonationRestrictedAction $action): bool;
}
