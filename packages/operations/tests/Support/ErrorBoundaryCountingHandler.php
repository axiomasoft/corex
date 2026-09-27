<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Throwable;

final class ErrorBoundaryCountingHandler implements ExceptionHandler
{
    /** @param list<Throwable> $reported */
    public function __construct(private array &$reported) {}

    public function report(Throwable $e): void
    {
        $this->reported[] = $e;
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function render($request, Throwable $e)
    {
        return response('unhandled', 500);
    }

    public function renderForConsole($output, Throwable $e): void {}
}
