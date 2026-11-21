<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ModuleId
{
    public function __construct(public string $package)
    {
        if (preg_match('~\A[a-z0-9]([_.-]?[a-z0-9]+)*/[a-z0-9](([_.]|-{1,2})?[a-z0-9]+)*\z~D', $package) !== 1) {
            throw new InvalidArgumentException('Expected a canonical Composer package name.');
        }
    }
}
