<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use Closure;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface StoredWorkRunner
{
    /**
     * Only call for a verified broker envelope or an owner-controlled durable work row.
     *
     * @template T
     *
     * @param  Closure(TenantContext): T  $work
     * @return T
     */
    public function runStored(DataScope $scope, StoredWorkProof $proof, Closure $work, ?OperationKey $attempt = null): mixed;
}
