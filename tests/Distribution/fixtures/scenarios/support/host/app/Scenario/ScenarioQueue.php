<?php

declare(strict_types=1);

namespace App\Scenario;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Queue;

final class ScenarioQueue
{
    public static function inScope(string $account, ?string $workspace, string $principal, Closure $callback): mixed
    {
        $guard = auth()->guard();
        $previous = $guard->user();

        try {
            return app(TenancyManager::class)->runFor(
                Scenario::account($account),
                $workspace === null ? null : Scenario::workspace($workspace),
                static function () use ($principal, $callback): mixed {
                    app(ScenarioPrincipalAuthority::class)->authenticate(app(TenantContextResolver::class)->current(), $principal);

                    return $callback();
                },
            );
        } finally {
            $guard->forgetUser();

            if ($previous !== null) {
                $guard->setUser($previous);
            }
        }
    }

    public static function push(ScenarioJob $job): string
    {
        $id = Queue::connection('database')->push($job, '', 'stage-one');

        return (string) $id;
    }

    public static function next(): void
    {
        resolve('queue.worker')->runNextJob('database', 'stage-one', new WorkerOptions(sleep: 0, maxTries: 1));
    }
}
