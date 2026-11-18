<?php

declare(strict_types=1);

namespace CoreX\Data\Internal;

use Closure;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CallableDataScopeAuthorizer implements DataScopeAuthorizer
{
    /**
     * @param  Closure(DataScope, string, string, list<string>, string):bool  $predicate
     */
    public function __construct(private Closure $predicate) {}

    public function authorize(
        DataScope $scope,
        string $entity,
        string $subjectId,
        array $fields,
        string $action,
    ): DataScopeAccessDecision {
        $allowed = ($this->predicate)($scope, $entity, $subjectId, $fields, $action);

        return new DataScopeAccessDecision(
            allowed: $allowed,
            reasonCode: $allowed ? 'callable.allowed' : 'callable.denied',
        );
    }
}
