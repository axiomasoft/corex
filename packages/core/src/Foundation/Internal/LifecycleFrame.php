<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Features\Data\FeatureFlagDefinition;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use Packman\Attributes\Internal;

#[Internal]
final class LifecycleFrame
{
    public bool $attaching = false;

    /** @var array<string,string>|null Current native purges inspected in the enclosing verified account frame. */
    public ?array $purges = null;

    /** Coordinator holding the physical lock during a single native purge witness read. */
    public ?DatabaseLifecyclePlans $purgeWitness = null;

    /** @var array<string,OwnerLease> */
    public array $leases = [];

    /** @var array{DataScope,CleanupAction}|null */
    public ?array $cleanup = null;

    /** @var ?Closure(FeatureFlagDefinition):?string */
    public ?Closure $admitManifestDefault = null;

    /** @var array{string,list<mixed>}|null */
    private ?array $call = null;

    /** @var array<int,true> */
    private array $visited = [];

    /** @param list<mixed> $arguments */
    public function enter(GuardedModuleLifecycle $guard, string $method, array $arguments): bool
    {
        if ($this->call !== [$method, $arguments]) {
            return false;
        }
        $key = spl_object_id($guard);

        if (isset($this->visited[$key])) {
            throw new LifecycleBlocked('Recursive native lifecycle dispatch.');
        }
        $this->visited[$key] = true;

        return true;
    }

    /** @template T
     * @param list<mixed> $arguments
     * @param Closure():T $call
     * @return T */
    public function invoke(GuardedModuleLifecycle $guard, string $method, array $arguments, Closure $call): mixed
    {
        $previous = $this->call;
        $visited = $this->visited;
        $this->call = [$method, $arguments];
        $this->visited = [spl_object_id($guard) => true];

        try {
            return $call();
        } finally {
            $this->call = $previous;
            $this->visited = $visited;
        }
    }
}
