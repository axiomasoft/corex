<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

/** Carries budget settlement evidence from a single tenant transaction. */
#[Api(since: '2.0.0')]
final readonly class BudgetAttempt
{
    public function __construct(public string $reservationId)
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $reservationId) !== 1) {
            throw new InvalidArgumentException('Invalid operation budget attempt.');
        }
    }
}
