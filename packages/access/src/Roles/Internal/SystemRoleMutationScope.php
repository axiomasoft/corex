<?php

declare(strict_types=1);

namespace CoreX\Access\Roles\Internal;

use Closure;
use CoreX\Access\Roles\Laravel\SystemRoleImmutable;

/**
 * Per-cycle state of the system-role mutation exemption (AC-21). Each model class that
 * carries {@see SystemRoleImmutable} has its own toggle: allowing
 * `Role` does not unlock `RoleMatrixRow`. Scoped, so a resident worker never carries the flag
 * from one request into the next.
 *
 * @internal Composition state of the roles module.
 */
final class SystemRoleMutationScope
{
    /** @var array<string, bool> */
    private array $allowed = [];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function allow(string $model, Closure $callback): mixed
    {
        $previous = $this->allowed[$model] ?? false;
        $this->allowed[$model] = true;

        try {
            return $callback();
        } finally {
            $this->allowed[$model] = $previous;
        }
    }

    public function isAllowed(string $model): bool
    {
        return $this->allowed[$model] ?? false;
    }
}
