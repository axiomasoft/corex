<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\ActualCost;

interface BudgetReservation
{
    public function settle(ActualCost $cost): void;

    public function reject(): void;
}
