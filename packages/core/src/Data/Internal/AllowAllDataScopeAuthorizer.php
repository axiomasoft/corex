<?php

declare(strict_types=1);

namespace CoreX\Data\Internal;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use Packman\Attributes\Internal;

#[Internal]
final class AllowAllDataScopeAuthorizer implements DataScopeAuthorizer
{
    public function authorize(
        DataScope $scope,
        string $entity,
        string $subjectId,
        array $fields,
        string $action,
    ): DataScopeAccessDecision {
        return new DataScopeAccessDecision(allowed: true, reasonCode: 'allow-all');
    }
}
