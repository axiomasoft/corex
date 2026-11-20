<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class AttemptEvidence
{
    public function __construct(public string $outcome, public string $reference)
    {
        if (! in_array($outcome, ['committed', 'rolled-back', 'reconciled', 'isolated', 'unknown'], true) || $reference === '' || strlen($reference) > 255) {
            throw new InvalidArgumentException('Invalid work outcome evidence.');
        }
    }
}
