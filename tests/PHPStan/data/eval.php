<?php

declare(strict_types=1);

namespace CoreX\Tests\PHPStan\Data\Eval;

final class EvalFixture
{
    public function violation(string $code): void
    {
        eval($code);
    }

    public function legal(string $code): string
    {
        return strtoupper($code);
    }
}
