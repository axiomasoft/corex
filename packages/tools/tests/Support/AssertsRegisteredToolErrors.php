<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Support;

use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use Packman\Errors\Contracts\ProblemRegistry;

trait AssertsRegisteredToolErrors
{
    private function assertToolError(ToolsErrorCode $expected, ?string $actual): void
    {
        self::assertSame($expected->value, $actual);
        self::assertSame($expected, resolve(ProblemRegistry::class)->fromWire($actual)->code);
    }
}
