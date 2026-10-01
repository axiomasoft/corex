<?php

declare(strict_types=1);

namespace CoreX\Data\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;

/** Explicit system-invocation adapter for an already admitted, trusted wakeup; never the default user authorizer. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SystemDataScopeAuthorization implements DataScopeAuthorizer
{
    public function authorize(DataScope $scope, string $entity, string $subjectId, array $fields, string $action): DataScopeAccessDecision
    {
        return new DataScopeAccessDecision(allowed: true, reasonCode: 'allow-all');
    }
}
