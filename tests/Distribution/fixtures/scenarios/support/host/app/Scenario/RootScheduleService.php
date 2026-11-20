<?php

declare(strict_types=1);

namespace App\Scenario;

use Closure;
use CoreX\Context\Contracts\NamedWorkPrincipal;
use CoreX\Context\Data\TenantContext;

final readonly class RootScheduleService implements NamedWorkPrincipal
{
    public function __construct(private ScenarioPrincipalAuthority $principals) {}

    public function run(TenantContext $context, string $serviceId, Closure $work): mixed
    {
        return $this->principals->forService($context, $serviceId, $work);
    }
}
