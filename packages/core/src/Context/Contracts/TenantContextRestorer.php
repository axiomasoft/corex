<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;

/**
 * Восстанавливает рантайм-контекст из durable-скоупа.
 *
 * Это НЕ {@see TenantContextResolver::current()}: current() отвечает «какой контекст у
 * процесса сейчас» и зависит от резолвера хоста/сессии, а restore() вызывается
 * обработчиком по durable-payload'у и обязан отказать, если процесс исполняется не в
 * том аккаунте, воркспейсе или соединении, которые записаны в скоупе.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TenantContextRestorer
{
    /** @throws ScopeContextMismatch */
    public function restore(DataScope $scope): TenantContext;
}
