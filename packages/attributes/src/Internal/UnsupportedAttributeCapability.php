<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal;

use RuntimeException;

final class UnsupportedAttributeCapability extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No qualified CoreX attributes engine is installed.', 0);
    }
}
