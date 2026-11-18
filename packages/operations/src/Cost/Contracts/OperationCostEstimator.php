<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Contracts;

use CoreX\Operations\Cost\Data\CostInput;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Definition\Data\Operation;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface OperationCostEstimator
{
    public function estimate(Operation $operation, CostInput $input): EstimatedCost;
}
