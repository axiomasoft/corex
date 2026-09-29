<?php

declare(strict_types=1);

namespace CoreX\Data\Contracts;

use CoreX\Data\DataScope;
use CoreX\Data\DataScopeAccessDecision;

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
