<?php

declare(strict_types=1);

namespace CoreX\Data\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class DataScopeAccessDecision
{
    public function __construct(
        public bool $allowed,
        public string $reasonCode,
    ) {
        if ($reasonCode === '' || strlen($reasonCode) > 128 || preg_match('/\A[a-z][a-z0-9_.-]{0,127}\z/D', $reasonCode) !== 1) {
            throw new InvalidArgumentException('Access decision reason code is invalid.');
        }
    }
}
