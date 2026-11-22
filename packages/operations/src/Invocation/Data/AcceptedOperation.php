<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class AcceptedOperation
{
    public function __construct(public string $operationRef, public string $statusUri)
    {
        if ($operationRef === '' || preg_match('~\A/(?!/)[^\s\\\\?#]+\z~D', $statusUri) !== 1) {
            throw new InvalidArgumentException('Invalid operation acceptance.');
        }
    }
}
