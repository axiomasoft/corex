<?php

declare(strict_types=1);

namespace CoreX\Data\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
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
