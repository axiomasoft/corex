<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class ConflictRule
{
    public function __construct(public string $kind)
    {
        if (! in_array($kind, ['source-newer', 'skip-existing', 'overwrite-if-version', 'create-only'], strict: true)) {
            throw new InvalidArgumentException('Unknown conflict rule.');
        }
    }
}
