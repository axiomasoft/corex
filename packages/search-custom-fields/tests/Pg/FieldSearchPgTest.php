<?php

declare(strict_types=1);

namespace CoreX\SearchCustomFields\Tests\Pg;

use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldProjectionCatalogue;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Internal\DatabaseSchemaHeads;
use CoreX\CustomFields\Tests\Pg\SchemaPgTestCase;
use CoreX\CustomFields\Types\Contracts\FieldConverterProvider;
use CoreX\CustomFields\Types\Contracts\FieldValueConverter;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\CustomFields\Values\Contracts\FieldValues;
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
use CoreX\Messaging\Contracts\BrokerDeliverySource;
use CoreX\Messaging\Contracts\CloudEventCodec;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Enums\BrokerReceipt;
use CoreX\Search\Errors\Exceptions\GenerationChanged;
use CoreX\Search\Errors\Exceptions\IndexNotReady;
use CoreX\Search\Errors\Exceptions\InvalidSearchCursor;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Index\Contracts\ActiveGenerations;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Internal\SearchOptions;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Postgres\Contracts\PostgresSnapshotAuthority;
use CoreX\Search\Postgres\Data\PostgresCorpusRows;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Contracts\SearchSource;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionField;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Data\ProjectionSelection;
use CoreX\Search\Projection\Data\RecordBatch;
use CoreX\Search\Projection\Data\RecordRefs;
use CoreX\Search\Projection\Data\SourceRecord;
use CoreX\Search\Projection\Data\SourceRecords;
use CoreX\Search\Projection\Enums\FieldType;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Contracts\SearchDriverRegistry;
use CoreX\Search\Query\Data\Candidate;
use CoreX\Search\Query\Data\CandidatePage;
use CoreX\Search\Query\Data\CandidateRequest;
use CoreX\Search\Query\Data\DriverCapabilities;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Query\Enums\StatisticStatus;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchCustomFields\Invalidation\Contracts\FieldSearchSubscriber;
use CoreX\SearchCustomFields\SearchCustomFieldsServiceProvider;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;

#[Group('pg')]
final class FieldSearchPgTest extends SchemaPgTestCase
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), SearchServiceProvider::class, SearchCustomFieldsServiceProvider::class, BridgeConversionProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('corex-search-custom-fields.enabled', true);
        $app['config']->set('corex-search.driver', 'bridge-test');
        $app['config']->set('corex-search.cursor_secret', str_repeat('s', 48));
    }

    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();
        $this->migrateSchemaSets(['axiomasoft/corex-search:tenant'], DB::getDefaultConnection());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(SearchCustomFieldsServiceProvider::class, force: true);
    }

    public function test_current_typed_projection_keeps_the_same_mapping_for_callers_and_closes_it_at_schema_switch(): void
    {
        $field = $this->field('measurement', 'decimal', constraints: ['scale' => 4, 'precision' => 40]);
        self::assertSame('completed', $this->drain($this->admit(FieldSchemaChange::add($field)))->state);
        $price = $this->field('price', 'money', constraints: ['scale' => 2, 'currency' => 'USD']);
        self::assertSame('completed', $this->drain($this->admit(FieldSchemaChange::add($price)))->state);
        $record = new RecordRef($this->entity->value, 'equipment/opaque-007');
        $exact = '9007199254740993.1200';
        $this->writeValue($field, $record->id, $exact);
        $caller = jsonbScope();
        CallerWorkScopeFixture::bind($this->app);
        $policy = new class($field->id->value, $record->id) implements DataScopeAuthorizer, FieldUsePolicy
        {
            public function __construct(private readonly string $field, private readonly string $record) {}

            public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
            {
                if ($scope->principalId === 'scoped-user' && $request->fields->fields === [$this->field]) {
                    return FieldUsePlan::scoped('record-specific-projection', [$this->record]);
                }

                return $scope->principalKind === 'user' && $request->fields->fields === [$this->field] && $request->use === FieldUse::Read
                    ? FieldUsePlan::denied() : FieldUsePlan::unconditional('service-projection');
            }

            public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
            {
                return new DataScopeAccessDecision($scope->principalKind !== 'user' || $fields->fields !== [$this->field] || $use !== FieldUse::Read
                    || ($scope->principalId === 'scoped-user' && $record->id === $this->record), 'owner-policy');
            }
        };
        $this->app->instance(FieldUsePolicy::class, $policy);
        $this->app->instance(DataScopeAuthorizer::class, $policy);
        $this->app->forgetInstance(DatabaseSchemaHeads::class);
        $this->app->forgetInstance(FieldCatalogue::class);
        $this->app->forgetInstance(FieldValues::class);

        $visible = $this->app->make(FieldCatalogue::class)->snapshot($caller, $this->entity);
        $owner = $this->app->make(FieldProjectionCatalogue::class)->snapshot($caller, $this->entity);
        self::assertNotContains(FieldUse::Read, $visible->fields[$field->id->value]->uses);
        self::assertContains(FieldUse::Read, $owner->fields[$field->id->value]->uses);
        self::assertSame(['id', 'type', 'uses', 'operators', 'localized'], array_keys(get_object_vars($owner->fields[$field->id->value])));
        self::assertSame($visible->revision->value, $owner->revision->value);
        self::assertSame($visible->mappingDigest, $owner->mappingDigest);
        self::assertSame([], $this->app->make(FieldValues::class)->read($caller, $record, new FieldSelection([$field->id->value]))->values);

        $projection = $this->app->make(SearchProjectionContributor::class);
        $mapping = $projection->describe(new ProjectionContext($this->scope, $this->entity))->revision;
        self::assertSame($mapping, $projection->describe(new ProjectionContext($caller, $this->entity))->revision);

        $this->app->instance(SearchSource::class, new class($record) implements SearchSource
        {
            public function __construct(private readonly RecordRef $record) {}

            public function page(DataScope $scope, EntityHandle $entity, ?RecordRef $after, int $limit): RecordBatch
            {
                return new RecordBatch(new RecordRefs($after === null ? [$this->record] : []), null);
            }

            public function load(DataScope $scope, RecordRefs $records): SourceRecords
            {
                $rows = array_map(static fn (RecordRef $ref): SourceRecord => new SourceRecord($ref, str_repeat('a', 64), new ProjectedValues([])), $records->records);

                return new SourceRecords($rows);
            }
        });
        $this->app->instance(ActiveGenerations::class, new class($mapping) implements ActiveGenerations
        {
            public function __construct(private readonly string $mapping) {}

            public function active(DataScope $scope, EntityHandle $entity): IndexTarget
            {
                return new IndexTarget($scope, $entity, 'bridge-test', 'primary', 1, IndexTarget::uidFor($scope, $entity, 'bridge-test', 'primary', 1), $this->mapping);
            }
        });
        $driver = new class($record) implements CandidateDriver
        {
            /** @var list<CandidateRequest> */
            public array $requests = [];

            public function __construct(private readonly RecordRef $record) {}

            public function capabilities(): DriverCapabilities
            {
                return new DriverCapabilities(filter: true, scopedCorpus: true);
            }

            public function candidates(CandidateRequest $request): CandidatePage
            {
                $this->requests[] = $request;
                $target = $request->generation;

                return new CandidatePage([new Candidate($target, $this->record, $target->documentId($this->record))], 'next');
            }
        };
        $this->app->make(SearchDriverRegistry::class)->register('bridge-test', static fn (): CandidateDriver => $driver);

        $search = $this->app->make(Search::class);
        $id = $field->id->value;
        $query = new SearchQuery($this->entity, filters: [new SearchFilter($id, ['0009007199254740993.12'])], fields: [$id], limit: 1);
        $page = $search->query($this->scope, $query);
        self::assertSame([$exact], $driver->requests[0]->filters[0]->values);
        self::assertSame($record, $page->hits[0]->record);
        self::assertSame([$id => $exact], $page->hits[0]->projection->values);
        self::assertNotNull($page->cursor);
        self::assertSame([], $search->query($caller, new SearchQuery($this->entity, fields: [$id], limit: 1))->hits[0]->projection->values);
        self::assertEquals(FieldUsePlan::unconditional('service-projection'), $policy->plan($caller, $this->entity, new FieldUseRequest(new FieldSelection([$id]), FieldUse::Filter)));
        expect(fn () => $search->query($caller, $query))->toThrow(SearchFeatureUnavailable::class);
        expect(fn () => $search->query($this->scope, new SearchQuery($this->entity, filters: [new SearchFilter($id, ['12.00001'])])))
            ->toThrow(SearchFeatureUnavailable::class);
        expect(fn () => $search->query($this->scope, new SearchQuery($this->entity, filters: [new SearchFilter($price->id->value, ['1.00'])])))
            ->toThrow(SearchFeatureUnavailable::class);
        self::assertCount(2, $driver->requests);
        $scoped = CallerWorkScopeFixture::scope($caller->accountId, $caller->workspaceId, 'scoped-user', $caller->restoreEpoch, $caller->bindingId);
        self::assertSame([$id => $exact], $search->query($scoped, $query)->hits[0]->projection->values);
        self::assertSame([$record->id], $driver->requests[2]->recordIds);

        self::assertSame('completed', $this->drain($this->admit(FieldSchemaChange::rename($this->entity, $field->id, 'reading')))->state);
        self::assertSame(0, DB::table('sys_inbox_messages')->count());
        expect(fn () => $search->query($this->scope, $query))->toThrow(IndexNotReady::class);
        expect(fn () => $search->query($this->scope, new SearchQuery($this->entity, filters: $query->filters, fields: $query->fields, limit: 1, cursor: $page->cursor)))
            ->toThrow(GenerationChanged::class);
        self::assertCount(3, $driver->requests);
    }

    public function test_relayed_field_facts_apply_once_and_out_of_order_schema_facts_keep_the_current_mapping(): void
    {
        try {
            DB::transaction(function (): void {
                $this->writeValue($this->declared, 'equipment/no-publish', 'cancelled');

                throw new RuntimeException('roll back');
            });
        } catch (RuntimeException $failure) {
            self::assertSame('roll back', $failure->getMessage());
        }

        self::assertSame(0, DB::table('sys_outbox_messages')->count());
        self::assertSame(0, DB::table('corex_search_dirty')->count());
        $write = $this->writeValue($this->declared, 'equipment/opaque-007', 'committed');
        $field = $this->field('before', 'int');
        self::assertSame('completed', $this->drain($this->admit(FieldSchemaChange::add($field)))->state);
        $older = $this->app->make(FieldProjectionCatalogue::class)->snapshot($this->scope, $this->entity);
        self::assertSame('completed', $this->drain($this->admit(FieldSchemaChange::rename($this->entity, $field->id, 'after')))->state);
        $newer = $this->app->make(FieldProjectionCatalogue::class)->snapshot($this->scope, $this->entity);
        self::assertNotSame($older->revision->value, $newer->revision->value);

        $transport = new class($this->app->make(CloudEventCodec::class)) implements BrokerDeliverySource, DurableMessagePublisher
        {
            /** @var array<string,string> */
            public array $deliveries = [];

            /** @var array<string,string> */
            public array $quarantine = [];

            public function __construct(private readonly CloudEventCodec $codec) {}

            public function publish(MessageEnvelope $message): void
            {
                $this->deliveries[$message->messageId] = $this->codec->encode($message);
            }

            public function read(ConsumerId $consumer, string $restrictedRef): ?string
            {
                return $consumer->name === FieldSearchSubscriber::CONSUMER ? ($this->deliveries[$restrictedRef] ?? null) : null;
            }

            public function quarantine(ConsumerId $consumer, string $restrictedRef, string $rawDigest, string $reason): void
            {
                $this->quarantine[$restrictedRef] = $rawDigest;
            }
        };
        $this->app->instance(DurableMessagePublisher::class, $transport);
        $this->app->instance(BrokerDeliverySource::class, $transport);
        self::assertSame(0, Artisan::call('corex:messaging:relay', ['--once' => true]));
        self::assertCount(5, $transport->deliveries);
        self::assertSame(5, DB::table('sys_outbox_messages')->where('status', 'published')->count());

        $subscriber = $this->app->make(FieldSearchSubscriber::class);
        self::assertSame(BrokerReceipt::Applied, $subscriber->consume($write->messageId));
        self::assertSame(BrokerReceipt::Duplicate, $subscriber->consume($write->messageId));
        self::assertSame($write->record->id, DB::table('corex_search_dirty')->sole()->record_id);
        self::assertSame(1, (int) DB::table('corex_search_dirty')->sole()->seq);

        $schema = DB::table('sys_outbox_messages')->where('name', 'fields.schema.changed')->pluck('message_id');
        $codec = $this->app->make(CloudEventCodec::class);
        $byRevision = [];
        foreach ($schema as $id) {
            $event = $codec->decode($transport->deliveries[$id]);
            $byRevision[$event->payload->values['target']] = $id;
        }
        $latest = $byRevision[$newer->revision->value];
        self::assertSame(BrokerReceipt::Applied, $subscriber->consume($latest));
        self::assertSame(BrokerReceipt::Duplicate, $subscriber->consume($latest));
        self::assertSame(BrokerReceipt::Applied, $subscriber->consume($byRevision[$older->revision->value]));
        $mapping = $this->app->make(SearchProjectionContributor::class)->describe(new ProjectionContext($this->scope, $this->entity))->revision;
        self::assertSame($mapping, DB::table('corex_search_rebuilds')->where('state', 'requested')->sole()->mapping_digest);
        self::assertSame($mapping, DB::table('corex_search_rebuilds')->where('state', 'coalesced')->sole()->mapping_digest);
        self::assertSame($newer->mappingDigest, DB::table('corex_search_rebuilds')->where('source_revision', $newer->revision->value)->sole()->source_mapping_digest);
        self::assertSame(1, DB::table('corex_search_dirty')->count());
        self::assertSame(3, DB::table('sys_inbox_messages')->count());
        $unsupported = DB::table('sys_outbox_messages')->where('name', 'fields.schema.requested')->value('message_id');
        self::assertSame(BrokerReceipt::Quarantined, $subscriber->consume($unsupported));
        self::assertSame(hash('sha256', $transport->deliveries[$unsupported]), $transport->quarantine[$unsupported]);
        self::assertSame(3, DB::table('sys_inbox_messages')->count());
    }

    public function test_live_postgres_keeps_authorized_hits_when_field_projection_cannot_join_the_exact_snapshot(): void
    {
        $ordinary = $this->field('rating', 'text');
        $field = FieldDefinition::fromArray([...$ordinary->toArray(), 'uses' => ['read', 'write', 'filter', 'aggregate']]);
        self::assertSame('completed', $this->drain($this->admit(FieldSchemaChange::add($field)))->state);
        $record = $this->writeValue($field, 'asset/007', '7')->record;
        $owner = $this->postgresOwner($field);
        $search = $this->app->make(Search::class);
        $base = $search->query($this->scope, new SearchQuery($this->entity, fields: ['category'], count: true));
        self::assertSame($record->id, $base->hits[0]->record->id);
        self::assertSame(['category' => 'pump'], $base->hits[0]->projection->values);
        self::assertSame(StatisticStatus::Unavailable, $base->count->status);
        self::assertNull($base->count->value);

        $id = $field->id->value;
        $faceted = $search->query($this->scope, new SearchQuery($this->entity, filters: [new SearchFilter($id, ['7'])],
            fields: [$id], count: true, facets: [$id]));
        self::assertCount(1, $faceted->hits);
        self::assertEquals($record, $faceted->hits[0]->record);
        self::assertSame([$id => '7'], $faceted->hits[0]->projection->values);
        self::assertSame(StatisticStatus::Unavailable, $faceted->count->status);
        self::assertSame(StatisticStatus::Unavailable, $faceted->facets->status);
        self::assertNull($faceted->facets->values);
        self::assertSame(0, $owner->snapshotChecks);
        self::assertSame(['off', 'off'], $owner->corpusModes);
    }

    public function test_live_postgres_checks_authenticated_mapping_before_lowering_an_old_field_type(): void
    {
        $field = $this->field('rating', 'text');
        self::assertSame('completed', $this->drain($this->admit(FieldSchemaChange::add($field)))->state);
        $record = $this->writeValue($field, 'asset/007', '7')->record;
        $this->writeValue($field, 'asset/008', '7');
        $owner = $this->postgresOwner($field);
        $search = $this->app->make(Search::class);
        $id = $field->id->value;
        $filtered = static fn (?string $cursor = null): SearchQuery => new SearchQuery(new EntityHandle($record->entity),
            filters: [new SearchFilter($id, ['7'])], fields: [$id], limit: 1, cursor: $cursor);
        $base = static fn (?string $cursor = null): SearchQuery => new SearchQuery(new EntityHandle($record->entity),
            fields: ['category'], limit: 1, cursor: $cursor);
        $filteredCursor = $search->query($this->scope, $filtered())->cursor;
        $baseCursor = $search->query($this->scope, $base())->cursor;
        self::assertNotNull($filteredCursor);
        self::assertNotNull($baseCursor);
        self::assertSame('asset/008', $search->query($this->scope, $filtered($filteredCursor))->hits[0]->record->id);
        self::assertSame('asset/008', $search->query($this->scope, $base($baseCursor))->hits[0]->record->id);
        $before = $owner->corpusModes;

        $replacement = $this->field('rating', 'int', id: $field->id);
        $op = $this->admit(FieldSchemaChange::revise($replacement, 'bridge.integer', 1));
        self::assertSame('completed', $this->drain($op)->state);
        expect(fn () => $search->query($this->scope, $filtered($filteredCursor)))->toThrow(GenerationChanged::class);
        expect(fn () => $search->query($this->scope, $base($baseCursor)))->toThrow(GenerationChanged::class);
        expect(fn () => $search->query($this->scope, $filtered($baseCursor)))->toThrow(InvalidSearchCursor::class);
        expect(fn () => $search->query($this->scope, $base(substr($baseCursor, 0, -3))))->toThrow(InvalidSearchCursor::class);
        expect(fn () => $search->query($this->scope, $filtered()))->toThrow(SearchFeatureUnavailable::class);
        self::assertSame($before, $owner->corpusModes);
        $current = $search->query($this->scope, new SearchQuery($this->entity, fields: [$id]));
        self::assertSame([$id => 7], $current->hits[0]->projection->values);
    }

    private function postgresOwner(FieldDefinition $field): BridgePostgresOwner
    {
        $this->app['config']->set('corex-search.driver', 'postgres');
        $this->app->forgetInstance(SearchOptions::class);
        $db = DB::connection();
        $db->statement('create table bridge_search_owner (asset_tag text primary key, category text, rating text, visible boolean)');
        $db->table('bridge_search_owner')->insert([
            ['asset_tag' => 'asset/007', 'category' => 'pump', 'rating' => '7', 'visible' => true],
            ['asset_tag' => 'asset/008', 'category' => 'valve', 'rating' => '7', 'visible' => true],
            ['asset_tag' => 'asset/009', 'category' => 'hidden', 'rating' => '7', 'visible' => false],
        ]);
        $owner = new BridgePostgresOwner($this->app->make(DatabaseManager::class), $field->id->value);
        $this->app->instance(BridgePostgresOwner::class, $owner);
        foreach ([PostgresCorpus::class, PostgresSnapshotAuthority::class, SearchSource::class] as $port) {
            $this->app->instance($port, $owner);
        }
        $this->app->tag(BridgePostgresOwner::class, SearchProjectionContributor::class);

        return $owner;
    }
}

final class BridgePostgresOwner implements PostgresCorpus, PostgresSnapshotAuthority, SearchProjectionContributor, SearchSource
{
    public int $snapshotChecks = 0;

    /** @var list<string> */
    public array $corpusModes = [];

    public function __construct(private readonly DatabaseManager $database, private readonly string $field) {}

    public function covers(DataScope $scope, ConnectionInterface $connection): bool
    {
        $this->snapshotChecks++;

        return $connection === $this->database->connection() && $connection->transactionLevel() === 1
            && (array) $connection->selectOne('show transaction_isolation') === ['transaction_isolation' => 'repeatable read']
            && (array) $connection->selectOne('show transaction_read_only') === ['transaction_read_only' => 'on'];
    }

    public function rows(IndexTarget $target, ConnectionInterface $connection): PostgresCorpusRows
    {
        if ($connection !== $this->database->connection()) {
            throw new LogicException('Unexpected PostgreSQL writer.');
        }
        $this->corpusModes[] = $connection->selectOne('show transaction_read_only')->transaction_read_only;

        return new PostgresCorpusRows($connection->table('bridge_search_owner')->where('visible', true), 'asset_tag',
            filterColumns: [$this->field => 'rating'], aggregateColumns: [$this->field => 'rating', 'category' => 'category']);
    }

    public function describe(ProjectionContext $context): ProjectionFields
    {
        return new ProjectionFields([new ProjectionField('category', FieldType::Text, [FieldUse::Read, FieldUse::Aggregate])], hash('sha256', 'equipment-category'));
    }

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
    {
        return new ProjectedValues([]);
    }

    public function page(DataScope $scope, EntityHandle $entity, ?RecordRef $after, int $limit): RecordBatch
    {
        throw new LogicException('The live PostgreSQL case does not use an index scan.');
    }

    public function load(DataScope $scope, RecordRefs $records): SourceRecords
    {
        $hits = [];
        foreach ($records->records as $ref) {
            $category = $this->database->connection()->table('bridge_search_owner')->where('visible', true)->where('asset_tag', $ref->id)->value('category');

            if (is_string($category)) {
                $hits[] = new SourceRecord($ref, hash('sha256', $category), new ProjectedValues(['category' => $category]));
            }
        }

        return new SourceRecords($hits);
    }
}

final class BridgeConversionProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag(BridgeIntegerConverter::class, FieldConverterProvider::class);
    }
}

final class BridgeIntegerConverter implements FieldConverterProvider, FieldValueConverter
{
    public function id(): string
    {
        return 'bridge.integer';
    }

    public function version(): int
    {
        return 1;
    }

    public function converter(): FieldValueConverter
    {
        return $this;
    }

    public function convert(FieldValue $value, FieldDefinition $from, FieldDefinition $to): FieldValue
    {
        $input = json_decode($value->document->canonicalJson, flags: JSON_THROW_ON_ERROR);

        return FieldValue::from((int) $input);
    }
}
