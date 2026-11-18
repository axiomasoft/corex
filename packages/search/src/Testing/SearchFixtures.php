<?php

declare(strict_types=1);

namespace CoreX\Search\Testing;

use Closure;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Search\Contracts\SearchHydrator;
use CoreX\Search\Data\CurrentSearchRecord;
use CoreX\Search\Data\SearchSnapshot;
use CoreX\Search\Internal\Query\PolicySearchHydrator;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SearchFixtures
{
    /** @param Closure(DataScope, RecordRef, SearchSnapshot):?CurrentSearchRecord $loadCurrent */
    public static function policyHydrator(Closure $loadCurrent, DataScopeAuthorizer $authorizer): SearchHydrator
    {
        return new PolicySearchHydrator(loadCurrent: $loadCurrent, authorizer: $authorizer);
    }
}
