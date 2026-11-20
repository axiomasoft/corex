<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class EngineProvider
{
    public function __construct(public string $namespace, public bool $statusLookup = false, public bool $idempotentResend = false)
    {
        if (preg_match('/\A[a-z][a-z0-9._-]{0,95}\z/D', $namespace) !== 1) {
            throw new InvalidArgumentException('Invalid workflow provider namespace.');
        }
    }
}
