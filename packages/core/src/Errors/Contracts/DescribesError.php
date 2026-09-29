<?php

declare(strict_types=1);

namespace CoreX\Errors\Contracts;

use CoreX\Errors\Data\ErrorDescriptor;

interface DescribesError
{
    public function errorDescriptor(): ErrorDescriptor;
}
