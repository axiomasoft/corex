<?php

declare(strict_types=1);

namespace CoreX\Api\Internal\Cost;

use CoreX\Api\Contracts\BudgetReservation;
use CoreX\Api\Data\ActualCost;
use CoreX\Api\Data\ThrottleStatus;
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
        $this->budget->settle($this->id, $cost);
    }

    #[Override]
    public function reject(): void
    {
        $this->budget->reject($this->id);
    }
}
