<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Errors\Exceptions\GenerationChanged;
use CoreX\Search\Errors\Exceptions\IndexNotReady;
use CoreX\Search\Errors\Exceptions\InvalidSearchCursor;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Errors\Exceptions\SearchWitnessChanged;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\SearchPage;
use CoreX\Search\Query\Data\SearchQuery;
use Packman\Attributes\Internal;
use Packman\Errors\Exceptions\CodedException;

#[Internal]
final readonly class AuthorizedSearch implements Search
{
    /** @param Closure(DataScope,SearchQuery):QuerySession $session */
    public function __construct(private WorkScopeRunner $runner, private CurrentWorkScope $current, private Closure $session) {}

    public function query(DataScope $scope, SearchQuery $query): SearchPage
    {
        $outcome = $this->runner->run($scope, function () use ($scope, $query): SearchPage|CodedException {
            $this->current->assert($scope);

            try {
                return ($this->session)($scope, $query)->run();
            } catch (GenerationChanged|IndexNotReady|InvalidSearchCursor|SearchFeatureUnavailable|SearchWitnessChanged $refusal) {
                // A read-only refusal settles this local admission before the caller receives its error.
                return $refusal;
            }
        });

        if ($outcome instanceof CodedException) {
            throw $outcome;
        }

        return $outcome;
    }
}
