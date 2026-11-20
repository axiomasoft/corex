<?php

declare(strict_types=1);

namespace CoreX\Search\Tests\Unit;

use CoreX\CoreServiceProvider;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\DataScopeAccessDecision;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Internal\SearchOptions;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Postgres\Contracts\PostgresSnapshotAuthority;
use CoreX\Search\Postgres\Data\PostgresCorpusRows;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Projection\Data\RecordBatch;
use CoreX\Search\Projection\Data\RecordRefs;
use CoreX\Search\Projection\Data\SourceRecords;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Query\Enums\StatisticStatus;
use CoreX\Search\SearchServiceProvider;
use CoreX\Search\Testing\SearchFixtures;
use CoreX\Search\Tests\PgTestCase;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\Group;

#[Group('pg')]
final class AuthorizedPostgresTest extends PgTestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, SearchServiceProvider::class];
    }

    public function test_public_statistics_require_a_shared_owner_and_acl_snapshot(): void
    {
        $fixture = new QueryFixture;
        $fixture->bind($this->app);
        $this->app['config']->set('corex-search.driver', 'postgres');
        $this->app->forgetInstance(SearchOptions::class);
        $db = DB::connection();
        $db->statement('CREATE TEMP TABLE query_owner_proof (id text primary key, name text, visible boolean, can_count boolean)');
        $db->table('query_owner_proof')->insert([
            ['id' => 'a', 'name' => 'pump', 'visible' => true, 'can_count' => true],
            ['id' => 'b', 'name' => 'pipe', 'visible' => true, 'can_count' => false],
            ['id' => 'c', 'name' => 'private', 'visible' => false, 'can_count' => true],
        ]);
        $owner = new PostgresQueryOwner($db);
        foreach ([PostgresCorpus::class, SearchSource::class, FieldUsePolicy::class, DataScopeAuthorizer::class] as $port) {
            $this->app->instance($port, $owner);
        }
        $search = $this->app->make(Search::class);
        $query = new SearchQuery($fixture->entity, fields: ['name'], count: true, facets: ['name']);
        $page = $search->query($fixture->scope, $query);
        self::assertSame(StatisticStatus::Unavailable, $page->count->status);
        self::assertSame(StatisticStatus::Unavailable, $page->facets->status);
        $this->app->instance(PostgresSnapshotAuthority::class, $owner);
        $page = $search->query($fixture->scope, $query);
        self::assertSame(StatisticStatus::Exact, $page->count->status);
        self::assertSame(1, $page->count->value);
        self::assertSame(['name' => ['"pump"' => 1]], $page->facets->values);
        self::assertSame(['a', 'b'], array_map(static fn ($hit) => $hit->record->id, $page->hits));
        $db->table('query_owner_proof')->where('id', 'a')->update(['visible' => false]);
        $page = $search->query($fixture->scope, $query);
        self::assertSame(0, $page->count->value);
        self::assertSame(['b'], array_map(static fn ($hit) => $hit->record->id, $page->hits));
    }
}

final readonly class PostgresQueryOwner implements DataScopeAuthorizer, FieldUsePolicy, PostgresCorpus, PostgresSnapshotAuthority, SearchSource
{
    public function __construct(private ConnectionInterface $owner) {}

    public function covers(DataScope $scope, ConnectionInterface $connection): bool
    {
        return $connection === $this->owner && $connection->transactionLevel() === 1
            && (array) $connection->selectOne('show transaction_isolation') === ['transaction_isolation' => 'repeatable read']
            && (array) $connection->selectOne('show transaction_read_only') === ['transaction_read_only' => 'on'];
    }

    public function rows(IndexTarget $target, ConnectionInterface $connection): PostgresCorpusRows
    {
        if ($connection !== $this->owner) {
            throw new LogicException('Wrong owner.');
        }

        return new PostgresCorpusRows($connection->table('query_owner_proof'), 'id', aggregateColumns: ['name' => 'name']);
    }

    public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
    {
        $q = $this->owner->table('query_owner_proof')->where('visible', true);

        if ($request->use === FieldUse::Aggregate) {
            $q->where('can_count', true);
        }
        $ids = $q->orderBy('id')->pluck('id')->all();

        return FieldUsePlan::scoped(hash('sha256', json_encode($ids, JSON_THROW_ON_ERROR)), $ids);
    }

    public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
    {
        $grant = $this->plan($scope, new EntityHandle($record->entity), new FieldUseRequest($fields, $use));

        return new DataScopeAccessDecision(in_array($record->id, $grant->recordIds, true), 'test.owner');
    }

    public function page(DataScope $scope, EntityHandle $entity, ?RecordRef $after, int $limit): RecordBatch
    {
        throw new LogicException('Unexpected scan.');
    }

    public function load(DataScope $scope, RecordRefs $records): SourceRecords
    {
        $rows = [];
        foreach ($records->records as $ref) {
            $name = $this->owner->table('query_owner_proof')->where('id', $ref->id)->value('name');

            if (is_string($name)) {
                $rows[] = SearchFixtures::record($ref, ['name' => $name]);
            }
        }

        return new SourceRecords($rows);
    }
}
