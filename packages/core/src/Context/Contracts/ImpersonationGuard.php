<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Enums\ImpersonationRestrictedAction;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface ImpersonationGuard
{
    public function assertAllowed(ImpersonationRestrictedAction $action): void;

    public function denies(ImpersonationRestrictedAction $action): bool;
}
