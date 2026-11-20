<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use Closure;
use CoreX\Context\Contracts\CentralWorkRunner;
use CoreX\Context\Laravel\WorkAuthentication;
use Illuminate\Container\Container;
use Packman\Attributes\Internal;

#[Internal]
final readonly class BoxedCentralWorkRunner implements CentralWorkRunner
{
    public function __construct(private Container $app, private WorkAuthentication $auth) {}

    public function run(Closure $work): mixed
    {
        $this->app->forgetScopedInstances();

        try {
            return $this->auth->run(null, $work);
        } finally {
            $this->app->forgetScopedInstances();
        }
    }
}
