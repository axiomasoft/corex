<?php

declare(strict_types=1);

namespace CoreX\Data\Internal;

use Closure;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use Packman\Attributes\Internal;

#[Internal]
final readonly class CallableDataScopeAuthorizer implements DataScopeAuthorizer
{
    /** @param Closure(DataScope, RecordRef, FieldSelection, FieldUse):bool $predicate */
    public function __construct(private Closure $predicate) {}

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $allowed = ($this->predicate)($scope, $record, $fields, $use);

        return new DataScopeAccessDecision($allowed, $allowed ? 'callable.allowed' : 'callable.denied');
    }
}
