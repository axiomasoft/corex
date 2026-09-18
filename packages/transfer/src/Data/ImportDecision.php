<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data;

use InvalidArgumentException;

final readonly class ImportDecision
{
    public function __construct(public string $status, public ?string $reason)
    {
        if (! in_array($status, ['apply', 'skip', 'conflict', 'invalid', 'unsupported'], strict: true)) {
            throw new InvalidArgumentException('Unknown import decision.');
        }

        if ($reason !== null) {
            Validation::opaque(value: $reason, name: 'import decision reason');
        }
    }
}
