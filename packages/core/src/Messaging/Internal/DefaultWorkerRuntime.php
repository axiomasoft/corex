<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use Closure;
use CoreX\Messaging\Contracts\WorkerRuntime;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Internal;

/**
 * Production `WorkerRuntime`: Octane detection via `app()->bound(...)` on the
 * Octane facade class-string (works even without `laravel/octane` installed —
 * `::class` resolves to a literal FQCN string at compile time, no autoload)
 * plus the `LARAVEL_OCTANE` env var Octane's server process exports to every
 * worker it spawns.
 *
 * @internal
 */
#[Internal]
final readonly class DefaultWorkerRuntime implements WorkerRuntime
{
    /** @param  Closure(): Application  $application  resolves the running application at call time. */
    public function __construct(private Closure $application) {}

    #[Override]
    public function isOctane(): bool
    {
        // getenv(), not env(): env() is banned outside config/ (returns null
        // once config is cached) — same seam CoreXTestCase uses for env reads
        // in application code.
        return ($this->application)()->bound('Laravel\Octane\Octane') || getenv('LARAVEL_OCTANE') !== false;
    }

    #[Override]
    public function runningInConsole(): bool
    {
        return ($this->application)()->runningInConsole();
    }
}
