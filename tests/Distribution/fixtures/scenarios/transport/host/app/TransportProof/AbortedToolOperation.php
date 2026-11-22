<?php

declare(strict_types=1);

namespace App\TransportProof;

use CoreX\Tools\Data\ToolResult;
use RuntimeException;

final class AbortedToolOperation extends RuntimeException
{
    public function __construct(public readonly ToolResult $result)
    {
        parent::__construct('The domain-key tool callback did not commit.');
    }
}
