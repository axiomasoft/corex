<?php

declare(strict_types=1);

namespace CoreX\Search\Testing;

use Closure;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\DataScope;
use CoreX\Data\RecordRef;
use CoreX\Search\Contracts\SearchHydrator;
use CoreX\Search\Internal\Query\PolicySearchHydrator;
use CoreX\Search\Support\CurrentSearchRecord;
use CoreX\Search\Support\SearchSnapshot;

final class SearchFixtures
{
    /** @param Closure(DataScope, RecordRef, SearchSnapshot):?CurrentSearchRecord $loadCurrent */
    public static function policyHydrator(Closure $loadCurrent, DataScopeAuthorizer $authorizer): SearchHydrator
    {
        return new PolicySearchHydrator(loadCurrent: $loadCurrent, authorizer: $authorizer);
    }
}
