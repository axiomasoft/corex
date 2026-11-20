<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Contracts;

use CoreX\Operations\Cost\Data\BudgetReceipt;
use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\EstimatedCost;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface DurableOperationBudget extends OperationBudget
{
    public function reserveAttempt(BudgetScope $scope, EstimatedCost $cost, string $reservationId): BudgetReservation;

    public function recover(BudgetReceipt $receipt): void;
}
