<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Data\ErrorDescriptor;

interface DescribesError
{
    public function errorDescriptor(): ErrorDescriptor;
}
