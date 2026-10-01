<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\EstimatedCost;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface OperationBudget
{
    public function reserve(BudgetScope $scope, EstimatedCost $cost): BudgetReservation;
}
