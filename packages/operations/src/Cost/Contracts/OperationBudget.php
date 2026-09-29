<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Contracts;

use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\EstimatedCost;

interface OperationBudget
{
    public function reserve(BudgetScope $scope, EstimatedCost $cost): BudgetReservation;
}
