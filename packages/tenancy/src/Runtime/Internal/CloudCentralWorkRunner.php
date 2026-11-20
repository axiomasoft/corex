<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use Closure;
use CoreX\Context\Contracts\CentralWorkRunner;
use CoreX\Context\Laravel\WorkAuthentication;
use Illuminate\Container\Container;
use Packman\Attributes\Internal;
use Tenant\Contracts\Runtime\TenantRuntime;
use Tenant\Data\Context\ProfileRef;
use Tenant\Data\Context\Purpose;
use Tenant\Data\Context\Selection;

#[Internal]
final readonly class CloudCentralWorkRunner implements CentralWorkRunner
{
    public function __construct(private TenantRuntime $runtime, private Container $app, private WorkAuthentication $auth, private ProfileRef $profile) {}

    public function run(Closure $work): mixed
    {
        try {
            return $this->runtime->run(Selection::central($this->profile), new Purpose('corex:central-job'), function () use ($work): mixed {
                $this->app->forgetScopedInstances();

                return $this->auth->run(null, $work);
            });
        } finally {
            $this->app->forgetScopedInstances();
        }
    }
}
