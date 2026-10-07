<?php

declare(strict_types=1);

namespace CoreX\Access\Roles\Laravel;

use Closure;
use CoreX\Access\Roles\Internal\SystemRoleMutationScope;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;

/**
 * @corex-model-extension Exact Role/RoleMatrixRow saving(), deleting(), affectsSystemRole() hooks.
 *
 * Blocks save()/delete() on a row that affects a system role (AC-21) —
 * the seeder and any future release path bypass it explicitly via
 * {@see withSystemMutationAllowed()}, everything else gets a 403-class
 * exception. Each class using this trait carries its own toggle: flagging
 * Role does not also unlock RoleMatrixRow.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
trait SystemRoleImmutable
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withSystemMutationAllowed(callable $callback): mixed
    {
        return self::systemMutationScope()->allow(model: self::class, callback: Closure::fromCallable($callback));
    }

    protected static function bootSystemRoleImmutable(): void
    {
        static::saving(static function (self $model): void {
            $model->guardSystemRoleMutation();
        });

        static::deleting(static function (self $model): void {
            $model->guardSystemRoleMutation();
        });
    }

    private function guardSystemRoleMutation(): void
    {
        if (self::systemMutationScope()->isAllowed(self::class)) {
            return;
        }

        if ($this->affectsSystemRole()) {
            throw new AuthorizationException('System role is immutable outside the seeder/release path; clone it to customize.');
        }
    }

    private static function systemMutationScope(): SystemRoleMutationScope
    {
        return Container::getInstance()->make(SystemRoleMutationScope::class);
    }

    abstract protected function affectsSystemRole(): bool;
}
