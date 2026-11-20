<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class BudgetReceipt
{
    public function __construct(public string $reservationId, public int $actualUnits)
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $reservationId) !== 1 || $actualUnits < 0) {
            throw new InvalidArgumentException('Invalid operation budget receipt.');
        }
    }
}
