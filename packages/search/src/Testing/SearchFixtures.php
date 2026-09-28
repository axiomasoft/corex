<?php

declare(strict_types=1);

namespace CoreX\Search\Testing;

use Closure;
use CoreX\Contracts\DataScopeAuthorizer;
use CoreX\Search\Contracts\SearchHydrator;
use CoreX\Search\Internal\Query\PolicySearchHydrator;
use CoreX\Search\Support\CurrentSearchRecord;
use CoreX\Search\Support\SearchSnapshot;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\RecordRef;

final class SearchFixtures
{
    /** @param Closure(DataScope, RecordRef, SearchSnapshot): ?CurrentSearchRecord $loadCurrent */
    public static function policyHydrator(Closure $loadCurrent, DataScopeAuthorizer $authorizer): SearchHydrator
    {
        return new PolicySearchHydrator(loadCurrent: $loadCurrent, authorizer: $authorizer);
    }
}
