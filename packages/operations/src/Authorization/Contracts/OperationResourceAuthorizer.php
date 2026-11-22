<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface OperationResourceAuthorizer
{
    public function authorize(OperationInvocation $invocation, ?StoredOperationOutcome $outcome = null): void;
}
