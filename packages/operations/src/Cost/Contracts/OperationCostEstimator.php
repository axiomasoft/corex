<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Contracts;

use CoreX\Operations\Cost\Data\CostInput;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Definition\Operation;

interface OperationCostEstimator
{
    public function estimate(Operation $operation, CostInput $input): EstimatedCost;
}
