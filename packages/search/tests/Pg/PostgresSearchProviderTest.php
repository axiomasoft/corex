<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Internal\Postgres\PostgresCandidateDriver;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Postgres\Contracts\PostgresSnapshotAuthority;
use CoreX\Search\Postgres\Data\PostgresCorpusRows;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\SearchCount;
use CoreX\Search\Query\Data\SearchFacets;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\Search\Query\Data\SearchPage;
use CoreX\Search\Query\Data\SearchSort;
use CoreX\Search\Query\Enums\StatisticStatus;
use CoreX\Search\Tests\PgTestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

final readonly class AdapterPostgresCorpus implements PostgresCorpus
{
    public function rows(IndexTarget $target, ConnectionInterface $connection): PostgresCorpusRows
    {
        return new PostgresCorpusRows(
            $connection->table('corex_search_adapter_test')
                ->where('workspace', $target->scope->workspaceId)->where('readable', true),
            'id', ['title' => 'title', 'subtitle' => 'subtitle'], ['status' => 'status'],
            ['rank' => 'rank', 'ref' => 'id'], ['status' => 'status'],
        );
    }
}

function adapterPostgresContext(string $workspace = 'a'): TenantContext
{
    return new TenantContext(new AccountRef('account', 'account', 'active', [], []),
        new WorkspaceRef($workspace, $workspace, 'project', $workspace, null));
}

/** @return array{PostgresCandidateDriver,DataScope,IndexTarget} */
function adapterPostgresDriver(string $workspace = 'a', ?PostgresSnapshotAuthority $authority = null): array
{
    $context = adapterPostgresContext($workspace);
    $scope = CallerWorkScopeFixture::forContext($context, 'operator', str_repeat('a', 64));
    $driver = new PostgresCandidateDriver(
        DB::connection(), new CallerWorkScopeFixture($context, static fn () => DB::connection(), $scope),
        new AdapterPostgresCorpus, authority: $authority,
    );

    return [$driver, $scope, $driver->target($scope, new EntityHandle('equipment'), str_repeat('c', 64))];
}

beforeEach(function (): void {
    DB::statement('DROP TABLE IF EXISTS corex_search_adapter_test');
    DB::statement('CREATE TABLE corex_search_adapter_test (workspace text not null, id text not null, title text not null, subtitle text, status text not null, rank int, readable bool not null, PRIMARY KEY (workspace,id))');
    DB::table('corex_search_adapter_test')->insert([
        ['workspace' => 'a', 'id' => 'equipment-1', 'title' => 'Centrifugal water', 'subtitle' => 'pump', 'status' => 'active', 'rank' => 8, 'readable' => true],
        ['workspace' => 'a', 'id' => 'equipment-2', 'title' => 'Archived pump', 'subtitle' => null, 'status' => 'archived', 'rank' => 8, 'readable' => true],
        ['workspace' => 'a', 'id' => 'equipment-3', 'title' => 'Portable pump', 'subtitle' => null, 'status' => 'active', 'rank' => null, 'readable' => true],
        ['workspace' => 'a', 'id' => 'equipment-4', 'title' => 'Confidential water pump', 'subtitle' => null, 'status' => 'active', 'rank' => 1, 'readable' => false],
        ['workspace' => 'b', 'id' => 'equipment-1', 'title' => 'B-only rotary pump', 'subtitle' => null, 'status' => 'active', 'rank' => 1, 'readable' => true],
    ]);
});

afterEach(function (): void {
    DB::disconnect('search_adapter_parallel');
    DB::statement('DROP TABLE IF EXISTS corex_search_adapter_test');
});

it('uses declared SQL columns and bound text for scoped phrases and filters', function (): void {
    [$driver, $scope, $target] = adapterPostgresDriver();
    $phrase = $driver->candidates(new CandidateRequest($scope, $target, '"water pump"', ['title', 'subtitle'], [], [], null, null, 10));
    $filtered = $driver->candidates(new CandidateRequest($scope, $target, 'pump', ['title', 'subtitle'],
        [new SearchFilter('status', ['active'])], [], ['equipment-1', 'equipment-2', 'equipment-4'], null, 10));
    $unmatched = $driver->candidates(new CandidateRequest($scope, $target, "unmatched' literal", ['title'], [], [], null, null, 10));

    expect($phrase->candidates)->toHaveCount(1)
        ->and($phrase->candidates[0]->record->id)->toBe('equipment-1')
        ->and($phrase->candidates[0]->documentId)->toBe($target->documentId($phrase->candidates[0]->record))
        ->and($phrase->candidates[0]->textWitnesses?->digests)->toBe([
            'title' => new ProjectedValues(['title' => 'Centrifugal water'])->fieldWitness('title'),
            'subtitle' => new ProjectedValues(['subtitle' => 'pump'])->fieldWitness('subtitle'),
        ])
        ->and(array_map(static fn (Candidate $candidate): string => $candidate->record->id, $filtered->candidates))->toBe(['equipment-1'])
        ->and($unmatched->candidates)->toBe([])
        ->and(fn () => $driver->candidates(new CandidateRequest($scope, $target, 'pump', ['unapproved'], [], [], null, null, 10)))
        ->toThrow(SearchFeatureUnavailable::class)
        ->and(fn () => $driver->candidates(new CandidateRequest($scope, $target, null, [], [new SearchFilter('unapproved', ['private'])], [], null, null, 10)))
        ->toThrow(SearchFeatureUnavailable::class);
});

it('continues stable keyset order with equal and null keys and an opaque scoped record tie break', function (): void {
    [$driver, $scope, $target] = adapterPostgresDriver();
    $request = static fn (?string $position): CandidateRequest => new CandidateRequest($scope, $target, null, [], [], [new SearchSort('rank', true)], null, $position, 1);
    $first = $driver->candidates($request(null));
    $second = $driver->candidates($request($first->nextPosition));
    $last = $driver->candidates($request($second->nextPosition));
    $end = $driver->candidates($request($last->nextPosition));

    expect($first->candidates[0]->record->id)->toBe('equipment-1')
        ->and($second->candidates[0]->record->id)->toBe('equipment-2')
        ->and($last->candidates[0]->record->id)->toBe('equipment-3')
        ->and($end->candidates)->toBe([])
        ->and($end->nextPosition)->toBeNull()
        ->and(fn () => $driver->candidates($request('wrong-position')))
        ->toThrow(SearchFeatureUnavailable::class);
});

it('rejects foreign namespace or owner connection before touching the mapped corpus', function (): void {
    [$driver, $scope, $target] = adapterPostgresDriver();
    [$otherDriver, $otherScope, $otherTarget] = adapterPostgresDriver('b');
    $page = $otherDriver->candidates(new CandidateRequest($otherScope, $otherTarget, null, [], [], [], null, null, 5));
    $context = adapterPostgresContext();
    config(['database.connections.search_adapter_parallel' => config('database.connections.pgsql')]);
    $bad = new PostgresCandidateDriver(DB::connection('search_adapter_parallel'),
        new CallerWorkScopeFixture($context, static fn () => DB::connection(), $scope), new AdapterPostgresCorpus);

    expect($page->candidates[0]->record->id)->toBe('equipment-1')
        ->and($page->candidates[0]->documentId)->not->toBe($target->documentId($page->candidates[0]->record))
        ->and(fn () => $driver->candidates(new CandidateRequest($scope, $otherTarget, null, [], [], [], null, null, 5)))
        ->toThrow(SearchFeatureUnavailable::class)
        ->and(fn () => $bad->target($scope, new EntityHandle('equipment'), $target->mappingDigest))
        ->toThrow(ScopeContextMismatch::class);
});

it('keeps the requested descending record order through continuation', function (): void {
    [$driver, $scope, $target] = adapterPostgresDriver();
    $request = static fn (?string $position): CandidateRequest => new CandidateRequest($scope, $target, null, [], [], [new SearchSort('ref', true)], null, $position, 1);
    $first = $driver->candidates($request(null));
    $second = $driver->candidates($request($first->nextPosition));
    $last = $driver->candidates($request($second->nextPosition));

    expect($first->candidates[0]->record->id)->toBe('equipment-3')
        ->and($second->candidates[0]->record->id)->toBe('equipment-2')
        ->and($last->candidates[0]->record->id)->toBe('equipment-1')
        ->and($driver->candidates($request($last->nextPosition))->candidates)->toBe([]);
});

it('provides exact statistics only inside a proven shared read only snapshot', function (): void {
    $owner = DB::connection();
    $authority = new class($owner) implements PostgresSnapshotAuthority
    {
        public function __construct(private readonly ConnectionInterface $owner) {}

        public function covers(DataScope $scope, ConnectionInterface $connection): bool
        {
            return $connection === $this->owner && $scope->workspaceId === 'a'
                && (array) $connection->selectOne('show transaction_isolation') === ['transaction_isolation' => 'repeatable read']
                && (array) $connection->selectOne('show transaction_read_only') === ['transaction_read_only' => 'on'];
        }
    };
    [$driver, $scope, $target] = adapterPostgresDriver(authority: $authority);
    [$unproven] = adapterPostgresDriver();
    $request = new CandidateRequest($scope, $target, 'pump', ['title', 'subtitle'], [], [], null, null, 20);
    config(['database.connections.search_adapter_parallel' => config('database.connections.pgsql')]);
    $parallel = DB::connection('search_adapter_parallel');

    $page = $driver->inSnapshot($scope, function (bool $exact) use ($driver, $request, $parallel): SearchPage {
        expect($exact)->toBeTrue()
            ->and($driver->candidates($request)->candidates)->toHaveCount(3);
        $parallel->table('corex_search_adapter_test')->where('workspace', 'a')->where('id', 'equipment-2')->update(['readable' => false]);
        $stats = $driver->statistics($request, true, ['status']);

        return new SearchPage([], null, new SearchCount(true, $stats->count), new SearchFacets(true, $stats->facets));
    });

    expect($page->count->status)->toBe(StatisticStatus::Exact)
        ->and($page->count->value)->toBe(3)
        ->and($page->facets->values)->toBe(['status' => ['"active"' => 2, '"archived"' => 1]])
        ->and($driver->candidates($request)->candidates)->toHaveCount(2)
        ->and(fn () => $driver->statistics($request, true, []))->toThrow(SearchFeatureUnavailable::class)
        ->and($unproven->inSnapshot($scope, static fn (bool $exact): SearchPage => new SearchPage(
            [], null, new SearchCount(true, $exact ? 0 : null), new SearchFacets(false, null),
        ))->count->status)->toBe(StatisticStatus::Unavailable);
});
