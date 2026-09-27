<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\CostInput;
use CoreX\Api\Data\EstimatedCost;
use CoreX\Api\Operation;

interface OperationCostEstimator
{
    public function estimate(Operation $operation, CostInput $input): EstimatedCost;
}
