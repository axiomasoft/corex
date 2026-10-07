<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Support;

use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;

trait AssertsRegisteredToolErrors
{
    private function assertToolError(ToolsErrorCode $expected, ?string $actual): void
    {
        self::assertSame($expected->value, $actual);
        self::assertSame($expected, resolve(ProblemRegistry::class)->fromWire($actual)->code);
    }
}
