<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Contracts;

use CoreX\Exceptions\ScopeContextMismatch;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\TenantContext;

/**
 * Восстанавливает рантайм-контекст из durable-скоупа.
 *
 * Это НЕ {@see TenantContextResolver::current()}: current() отвечает «какой контекст у
 * процесса сейчас» и зависит от резолвера хоста/сессии, а restore() вызывается
 * обработчиком по durable-payload'у и обязан отказать, если процесс исполняется не в
 * том аккаунте, воркспейсе или соединении, которые записаны в скоупе.
 */
interface TenantContextRestorer
{
    /** @throws ScopeContextMismatch */
    public function restore(DataScope $scope): TenantContext;
}
