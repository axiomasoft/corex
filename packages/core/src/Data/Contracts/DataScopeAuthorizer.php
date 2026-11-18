<?php

declare(strict_types=1);

namespace CoreX\Data\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

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
