<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Support;

use CoreX\Tools\Data\ToolResult;
use RuntimeException;

/** Carries a safe result out of the transaction after rollback. */
final class ToolEffectRollback extends RuntimeException
{
    public function __construct(public readonly ToolResult $result)
    {
        parent::__construct('Tool effect must roll back.');
    }
}
