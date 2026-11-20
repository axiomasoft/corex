<?php

declare(strict_types=1);

namespace CoreX\Search\Tests\Unit;

use Closure;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\CoreServiceProvider;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Search\Errors\Exceptions\GenerationChanged;
use CoreX\Search\Errors\Exceptions\IndexNotReady;
use CoreX\Search\Errors\Exceptions\InvalidSearchCursor;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Errors\Exceptions\SearchWitnessChanged;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Query\Data\SearchSort;
use CoreX\Search\Query\Enums\StatisticStatus;
use CoreX\Search\SearchServiceProvider;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use LogicException;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class PolicySearchHydratorTest extends TestCase
{
    private QueryFixture $fixture;

    private Search $search;

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, SearchServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = new QueryFixture;
        $this->fixture->bind($this->app);
        $this->search = $this->app->make(Search::class);
    }

    public function test_authorizes_record_fields_and_current_filter_values_independently(): void
    {
        $f = $this->fixture;
        $f->deniedIds = ['c'];
        $f->deniedReads = ['paid'];
        $query = new SearchQuery($f->entity, filters: [new SearchFilter('paid', [true])], fields: ['name', 'paid'], count: true, facets: ['paid']);
        $page = $this->search->query($f->scope, $query);

        self::assertSame(['a'], array_map(fn ($hit) => $hit->record->id, $page->hits));
        self::assertSame(['name' => 'Pump'], $page->hits[0]->projection->values);
        self::assertNull($page->cursor);
        self::assertSame(StatisticStatus::Unavailable, $page->count->status);
        self::assertSame(StatisticStatus::Unavailable, $page->facets->status);
    }

    public function test_indexed_text_must_still_match_current_owner_field_content(): void
    {
        $f = $this->fixture;
        $f->deniedReads = ['name'];
        $query = new SearchQuery($f->entity, text: 'pump', searchFields: ['name'], fields: ['name', 'paid']);
        $f->rows['a']['paid'] = false;
        $page = $this->search->query($f->scope, $query);
        self::assertSame('a', $page->hits[0]->record->id);
        self::assertSame(['paid' => false], $page->hits[0]->projection->values);

        $f->rows['a']['name'] = 'Valve';
        self::assertSame([], $this->search->query($f->scope, $query)->hits);

        $f->indexed['a'] = $f->rows['a'];
        $page = $this->search->query($f->scope, new SearchQuery($f->entity, text: 'valve', searchFields: ['name']));
        self::assertSame(['a', 'c'], array_map(static fn ($hit) => $hit->record->id, $page->hits));
    }

    public function test_an_owner_rename_during_hydration_removes_the_match_before_release(): void
    {
        $f = $this->fixture;
        $f->projectHook = static function (QueryFixture $f): void {
            $f->rows['a']['name'] = 'Renamed';
            $f->projectHook = null;
        };
        $page = $this->search->query($f->scope, new SearchQuery($f->entity, text: 'pump', searchFields: ['name']));
        self::assertSame([], $page->hits);
        self::assertSame(2, $f->driverCalls);
    }

    public static function uses(): array
    {
        return [['read|'], ['search|name'], ['filter|paid'], ['sort|name'], ['aggregate|paid'], ['aggregate|']];
    }

    #[DataProvider('uses')]
    public function test_denied_uses_fail_before_candidate_access(string $denied): void
    {
        $f = $this->fixture;
        $f->deniedUses = [$denied];
        $q = new SearchQuery($f->entity, 'pump', ['name'], [new SearchFilter('paid', [true])], [new SearchSort('name')], ['name'], count: true, facets: ['paid']);

        try {
            $this->search->query($f->scope, $q);
            self::fail('Denied field use produced a result.');
        } catch (SearchFeatureUnavailable) {
            self::assertSame(0, $f->driverCalls);
            self::assertSame(0, $f->loads);
        }
    }

    public function test_scoped_predicates_intersect_before_lowering_and_unsupported_constraints_refuse(): void
    {
        $f = $this->fixture;
        $f->corpora = ['read|' => ['a', 'b'], 'sort|name' => ['b', 'c']];
        $q = new SearchQuery($f->entity, sort: [new SearchSort('name')]);
        self::assertSame('b', $this->search->query($f->scope, $q)->hits[0]->record->id);
        self::assertSame(['b'], $f->receivedCorpus);
        $f->allowCorpus = false;
        $f->driverCalls = 0;

        try {
            $this->search->query($f->scope, $q);
            self::fail('Unlowerable corpus accepted.');
        } catch (SearchFeatureUnavailable) {
            self::assertSame(0, $f->driverCalls);
        }
    }

    public static function malformed(): array
    {
        return [['document'], ['entity'], ['ref'], ['scope'], ['uid']];
    }

    #[DataProvider('malformed')]
    public function test_checks_entire_candidate_batch_before_loading_any_owner(string $kind): void
    {
        $f = $this->fixture;
        $f->tamper = static function (Candidate $c) use ($kind): Candidate {
            if ($c->record->id !== 'b') {
                return $c;
            }
            $t = $c->generation;
            $scope = $kind === 'scope' ? CallerWorkScopeFixture::scope('other', null, 'user', str_repeat('b', 64)) : $t->scope;
            $g = $kind === 'uid' ? 2 : 1;
            $target = new IndexTarget($scope, $t->entity, $t->backend, $t->cluster, $g, IndexTarget::uidFor($scope, $t->entity, $t->backend, $t->cluster, $g), $t->mappingDigest);
            $record = match ($kind) {
                'entity' => new RecordRef('employees', 'b'),
                'ref' => new RecordRef('equipment', 'substituted'),
                default => $c->record,
            };

            return new Candidate($target, $record, $kind === 'document' ? 'wrong' : $c->documentId);
        };

        try {
            $this->search->query($f->scope, new SearchQuery($f->entity));
            self::fail('Forged candidate accepted.');
        } catch (SearchFeatureUnavailable) {
            self::assertSame(0, $f->loads);
        }
    }

    public function test_cursor_binds_scope_query_policy_and_active_generation_before_candidate_lookup(): void
    {
        $f = $this->fixture;
        $fresh = fn () => $this->search->query($f->scope, new SearchQuery($f->entity, limit: 1))->cursor;
        $cursor = $fresh();
        self::assertStringStartsWith('q2.', $cursor);
        $next = fn ($token) => $this->search->query($f->scope, new SearchQuery($f->entity, limit: 1, cursor: $token));
        self::assertSame('b', $next($cursor)->hits[0]->record->id);
        foreach ([substr($cursor, 0, -2).'--', 'v1.invalid'] as $bad) {
            $before = $f->driverCalls;

            try {
                $next($bad);
                self::fail();
            } catch (InvalidSearchCursor) {
                self::assertSame($before, $f->driverCalls);
            }
        }
        $before = $f->driverCalls;

        try {
            $this->search->query($f->scope, new SearchQuery($f->entity, fields: ['paid'], limit: 1, cursor: $cursor));
            self::fail();
        } catch (InvalidSearchCursor) {
            self::assertSame($before, $f->driverCalls);
        }
        $f->policyEpoch++;

        try {
            $next($cursor);
            self::fail();
        } catch (InvalidSearchCursor) {
            self::assertSame($before, $f->driverCalls);
        }
        $cursor = $fresh();
        $before = $f->driverCalls;
        $f->generation++;

        try {
            $next($cursor);
            self::fail();
        } catch (GenerationChanged) {
            self::assertSame($before, $f->driverCalls);
        }
    }

    public function test_read_only_query_refusals_settle_verified_work_while_unexpected_errors_abort(): void
    {
        $f = $this->fixture;
        $cursor = $this->search->query($f->scope, new SearchQuery($f->entity, limit: 1))->cursor;
        self::assertNotNull($cursor);
        $runner = new class($this->app->make(WorkScopeRunner::class)) implements WorkScopeRunner
        {
            public int $completed = 0;

            public function __construct(private readonly WorkScopeRunner $verified) {}

            public function run(DataScope $scope, Closure $work): mixed
            {
                $result = $this->verified->run($scope, $work);
                $this->completed++;

                return $result;
            }
        };
        $this->app->instance(WorkScopeRunner::class, $runner);
        $search = $this->app->make(Search::class);
        $f->generation++;
        $before = $f->driverCalls;

        try {
            $search->query($f->scope, new SearchQuery($f->entity, limit: 1, cursor: $cursor));
            self::fail('Superseded generation was accepted.');
        } catch (GenerationChanged) {
            self::assertSame(1, $runner->completed);
            self::assertSame($before, $f->driverCalls);
        }

        $failure = new LogicException('Unexpected query driver failure.');
        $f->tamper = static fn (): never => throw $failure;

        try {
            $search->query($f->scope, new SearchQuery($f->entity, limit: 1));
            self::fail('Driver failure was lost.');
        } catch (LogicException $actual) {
            self::assertSame($failure, $actual);
            self::assertSame(1, $runner->completed);
        }
    }

    public function test_ignores_indexing_principal_in_target_but_authenticates_query_principal_in_cursor(): void
    {
        $f = $this->fixture;
        $f->tamper = static function (Candidate $c): Candidate {
            $t = $c->generation;
            $s = CallerWorkScopeFixture::scope($t->scope->accountId, null, 'index-service', $t->scope->restoreEpoch);

            return new Candidate(new IndexTarget($s, $t->entity, $t->backend, $t->cluster, $t->generation, $t->uid, $t->mappingDigest), $c->record, $c->documentId);
        };
        $page = $this->search->query($f->scope, new SearchQuery($f->entity, limit: 1));
        self::assertSame('a', $page->hits[0]->record->id);
        $other = CallerWorkScopeFixture::scope($f->scope->accountId, null, 'other-user', $f->scope->restoreEpoch);
        CallerWorkScopeFixture::bind($this->app, $other);
        $before = $f->driverCalls;

        try {
            $this->app->make(Search::class)->query($other, new SearchQuery($f->entity, limit: 1, cursor: $page->cursor));
            self::fail('Cursor accepted for a different verified principal.');
        } catch (InvalidSearchCursor) {
            self::assertSame($before, $f->driverCalls);
        }
    }

    public function test_checks_last_owner_and_policy_witness_and_restarts_only_once(): void
    {
        $f = $this->fixture;
        $f->projectHook = static function (QueryFixture $fixture): void {
            $fixture->policyEpoch++;
            $fixture->deniedReads = ['name'];
            $fixture->projectHook = null;
        };
        $page = $this->search->query($f->scope, new SearchQuery($f->entity, fields: ['name'], limit: 1));
        self::assertSame([], $page->hits[0]->projection->values);
        self::assertSame(2, $f->driverCalls);
        $f->deniedReads = [];
        $f->driverCalls = 0;
        $f->projectHook = static function (QueryFixture $fixture): void {
            $fixture->policyEpoch++;
        };

        try {
            $this->search->query($f->scope, new SearchQuery($f->entity, fields: ['name'], limit: 1));
            self::fail();
        } catch (SearchWitnessChanged) {
            self::assertSame(2, $f->driverCalls);
        }
    }

    public function test_caps_scans_at_one_thousand_and_continues_past_a_fully_redacted_page(): void
    {
        $f = $this->fixture;
        $f->ids = array_map(strval(...), range(1, 1001));
        $f->rows = ['1001' => ['name' => 'Last authorized record']];
        $q = new SearchQuery($f->entity, fields: ['name'], limit: 100);
        $page = $this->search->query($f->scope, $q);
        self::assertSame([], $page->hits);
        self::assertSame(1000, $f->seen);
        self::assertNotNull($page->cursor);
        $page = $this->search->query($f->scope, new SearchQuery($f->entity, fields: ['name'], limit: 100, cursor: $page->cursor));
        self::assertSame('1001', $page->hits[0]->record->id);
        self::assertNull($page->cursor);
    }

    public function test_before_first_publication_refuses_index_access(): void
    {
        $f = $this->fixture;
        $f->generation = 0;

        try {
            $this->search->query($f->scope, new SearchQuery($f->entity));
            self::fail();
        } catch (IndexNotReady) {
            self::assertSame(0, $f->driverCalls);
            self::assertSame(0, $f->loads);
        }
    }
}
