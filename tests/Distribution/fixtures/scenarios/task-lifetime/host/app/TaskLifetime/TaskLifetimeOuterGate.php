<?php

declare(strict_types=1);

namespace App\TaskLifetime;

use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Lifecycle\ModuleActivationGate;
use Packman\Modules\Data\Lifecycle\ModuleActivationDecision;

final class TaskLifetimeOuterGate implements ModuleActivationGate
{
    public int $checks = 0;

    public function __construct(private readonly ModuleActivationGate $next) {}

    public function check(ContextScopeInterface $ctx, string $module, string $edition): ModuleActivationDecision
    {
        $this->checks++;
        $decision = $this->next->check($ctx, $module, $edition);

        if ($module === 'axiomasoft/corex-tasks' && $edition === 'outer-restricted') {
            return ModuleActivationDecision::deny('Outer edition restricted', 'outer-upgrade');
        }

        return $decision;
    }
}
