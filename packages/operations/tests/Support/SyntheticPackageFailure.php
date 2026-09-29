<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Support;

use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Data\ErrorDescriptor;
use RuntimeException;

final class SyntheticPackageFailure extends RuntimeException implements DescribesError
{
    public function __construct(private readonly ErrorDescriptor $descriptor)
    {
        parent::__construct('secret internals');
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return $this->descriptor;
    }
}
