<?php

declare(strict_types=1);

namespace App\TaskLifetime;

use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Lifecycle\ModuleActivationGate;
use Packman\Modules\Data\Lifecycle\ModuleActivationDecision;

final class TaskLifetimeActivationGate implements ModuleActivationGate
{
    public int $checks = 0;

    public function __construct(private readonly bool $workspaceEdition) {}

    public function check(ContextScopeInterface $ctx, string $module, string $edition): ModuleActivationDecision
    {
        $this->checks++;

        return $edition === 'restricted' || ($module === 'axiomasoft/corex-tasks' && ! $this->workspaceEdition)
            ? ModuleActivationDecision::deny('Host declined edition', 'upgrade')
            : ModuleActivationDecision::allow();
    }
}
