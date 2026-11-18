<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Contracts;

use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\EstimatedCost;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface OperationBudget
{
    public function reserve(BudgetScope $scope, EstimatedCost $cost): BudgetReservation;
}
