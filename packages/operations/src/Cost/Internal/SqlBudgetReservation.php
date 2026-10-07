<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Internal;

use CoreX\Operations\Cost\Contracts\BudgetReservation;
use CoreX\Operations\Cost\Data\ActualCost;
use CoreX\Operations\Cost\Data\ThrottleStatus;
use Override;

/** @internal */
final readonly class SqlBudgetReservation implements BudgetReservation
{
    public function __construct(
        private SqlOperationBudget $budget,
        private string $id,
        public ThrottleStatus $throttleStatus,
    ) {}

    #[Override]
    public function settle(ActualCost $cost): void
    {
        $this->budget->settle(id: $this->id, cost: $cost);
    }

    #[Override]
    public function reject(): void
    {
        $this->budget->reject($this->id);
    }
}
