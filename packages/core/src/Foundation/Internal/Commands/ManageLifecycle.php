<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal\Commands;

use CoreX\Foundation\Contracts\LifecyclePlans;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\LifecycleStatus;
use CoreX\Foundation\Data\ModuleId;
use CoreX\Foundation\Data\PlanDigest;
use CoreX\Foundation\Enums\LifecycleAction;
use CoreX\Foundation\Enums\LifecyclePhase;
use CoreX\Foundation\Internal\LifecycleOperator;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final class ManageLifecycle extends Command
{
    protected $signature = 'corex:lifecycle {operation : plan, inspect or apply} {target : Module or plan ID} {argument? : Action or confirmed SHA-256 digest}';

    protected $description = 'Prepare, inspect or apply an authorized account lifecycle plan';

    public function handle(LifecyclePlans $plans, LifecycleOperator $operator): int
    {
        $scope = $operator->current();
        $target = $this->argument('target');
        $value = (string) $this->argument('argument');
        $result = match ($this->argument('operation')) {
            'plan' => $plans->plan(scope: $scope, module: new ModuleId($target), action: LifecycleAction::from($value)),
            'inspect' => $plans->inspect(scope: $scope, ref: new LifecyclePlanRef($target)),
            'apply' => $plans->apply(scope: $scope, ref: new LifecyclePlanRef($target), digest: new PlanDigest($value)),
            default => throw new InvalidArgumentException('Expected plan, inspect or apply.'),
        };
        $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $result instanceof LifecycleStatus && $result->phase === LifecyclePhase::Blocked ? self::FAILURE : self::SUCCESS;
    }
}
