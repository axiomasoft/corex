<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\BudgetScope;
use CoreX\Api\Data\EstimatedCost;

interface OperationBudget
{
    public function reserve(BudgetScope $scope, EstimatedCost $cost): BudgetReservation;
}
