<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\DataScopeAccessDecision;

interface DataScopeAuthorizer
{
    /**
     * @param  list<string>  $fields
     */
    public function authorize(
        DataScope $scope,
        string $entity,
        string $subjectId,
        array $fields,
        string $action,
    ): DataScopeAccessDecision;
}
