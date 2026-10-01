<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Cost\Data\ActualCost;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface BudgetReservation
{
    public function settle(ActualCost $cost): void;

    public function reject(): void;
}
