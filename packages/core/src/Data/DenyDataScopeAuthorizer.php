<?php

declare(strict_types=1);

namespace CoreX\Data;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScopeAccessDecision;

/** @internal */
final class DenyDataScopeAuthorizer implements DataScopeAuthorizer
{
    public function authorize(
        DataScope $scope,
        string $entity,
        string $subjectId,
        array $fields,
        string $action,
    ): DataScopeAccessDecision {
        return new DataScopeAccessDecision(allowed: false, reasonCode: 'deny.default');
    }
}
