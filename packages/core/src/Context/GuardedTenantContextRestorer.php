<?php

declare(strict_types=1);

namespace CoreX\Context;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\DataScope;
use Override;

/**
 * Реализация по умолчанию: контекст процесса уже установлен вызывающей стороной
 * (sweeper, bootstrapper очереди, boxed-резолвер), а restore() доказывает, что он
 * совпадает со скоупом. Расхождение — отказ, а не молчаливая работа в чужом аккаунте.
 */
final readonly class GuardedTenantContextRestorer implements TenantContextRestorer
{
    public function __construct(private TenantContextResolver $resolver) {}

    #[Override]
    public function restore(DataScope $scope): TenantContext
    {
        $current = $this->resolver->current();

        if ($current->account->id !== $scope->accountId) {
            throw ScopeContextMismatch::account(expected: $scope->accountId, actual: $current->account->id);
        }

        if (($current->workspace?->id) !== $scope->workspaceId) {
            throw ScopeContextMismatch::workspace(
                expected: $scope->workspaceId,
                actual: $current->workspace?->id,
            );
        }

        if ($current->isBoxed() !== $scope->boxed) {
            throw ScopeContextMismatch::profile(expectedBoxed: $scope->boxed);
        }

        return $current;
    }
}
