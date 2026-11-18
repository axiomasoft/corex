<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Contracts;

use CoreX\Operations\Cost\Data\ActualCost;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface BudgetReservation
{
    public function settle(ActualCost $cost): void;

    public function reject(): void;
}
