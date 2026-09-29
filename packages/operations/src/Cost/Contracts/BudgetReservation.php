<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Contracts;

use CoreX\Operations\Cost\Data\ActualCost;

interface BudgetReservation
{
    public function settle(ActualCost $cost): void;

    public function reject(): void;
}
