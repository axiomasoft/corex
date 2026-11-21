<?php

declare(strict_types=1);

use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\ProviderRecoveryAuthority;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use CoreX\Context\Laravel\ScopeOptions;
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
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Search\Index\Contracts\ActiveGenerations;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Contracts\IndexLookupAccess;
use CoreX\Search\Index\Contracts\IndexWorker;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Index\Enums\IndexOutcome;
use CoreX\Search\Lane\Contracts\SearchInvalidations;
use CoreX\Search\Lane\Contracts\SearchMappingInvalidations;
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
use CoreX\Search\Query\Contracts\SearchDriverRegistry;
use CoreX\Search\Rebuild\Contracts\RebuildWorker;
use CoreX\Search\Rebuild\Contracts\SearchRebuilds;
use CoreX\Search\Rebuild\Data\GenerationRef;
use CoreX\Search\Rebuild\Data\RebuildInput;
use CoreX\Search\Rebuild\Data\RebuildRef;
use CoreX\Search\SearchServiceProvider;
use CoreX\Search\Tests\PgTestCase;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;

require_once dirname(__DIR__, 4).'/tests/Support/LocalWorkContexts.php';
require_once dirname(__DIR__, 4).'/tests/Support/WorkScopeFixture.php';

abstract class SearchIndexLifecycleTestCase extends PgTestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, SearchServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('corex-search.driver', 'test');
    }
}

uses(SearchIndexLifecycleTestCase::class)->group('pg');

beforeEach(function (): void {
    $this->clock = new FrozenClock(new DateTimeImmutable);
    app()->instance(ClockInterface::class, $this->clock);
    $owner = DB::connection();
    $ownerName = $owner->getDatabaseName();
    $controlName = getenv('PGSQL_CONTROL_DATABASE') ?: '';

    if (! str_ends_with($controlName, '_test') || ! str_ends_with($ownerName, '_test') || $controlName === $ownerName) {
        throw new LogicException('Search requires separate disposable owner and control databases.');
    }
    config(['database.connections.index_control' => [...$owner->getConfig(), 'name' => 'index_control', 'database' => $controlName], 'corex-search.driver' => 'test',
        'corex-search.cluster' => 'primary']);
    expect($owner->selectOne('select current_database() as db')->db)->toBe($ownerName);
    $control = DB::connection('index_control');
    expect($control->selectOne('select current_database() as db')->db)->toBe($controlName);
    foreach (['corex_provider_observations', 'corex_scope_tickets', 'corex_scope_routes'] as $table) {
        $control->getSchemaBuilder()->dropIfExists($table);
    }
    ScopeControlSchema::install($control);
    $this->scope = new DataScope('index-install', 'index-account', null, 'index-owner', 1, 'incarnation', str_repeat('a', 64), 'service', 'search-worker');
    $this->entity = new EntityHandle('equipment');
    $context = new TenantContext(new AccountRef('index-account', 'index-account', 'active', [], []));
    LocalWorkContexts::install(app())->initialize($context->account);
    $principal = new WorkScopeFixture(app(), $control, 'service', 'search-worker');
    app()->scoped(WorkPrincipalAuthority::class, static fn () => $principal);
    $verifier = new class($owner, $this->scope) implements PhysicalBindingVerifier
    {
        public function __construct(private ConnectionInterface $owner, private DataScope $scope) {}

        public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
        {
            $binding->assertScope($this->scope);

            if ($connection !== $this->owner || $context->account->id !== $this->scope->accountId) {
                throw new ScopeContextMismatch('Unexpected physical index owner.');
            }
        }
    };
    app()->scoped(PhysicalBindingVerifier::class, static fn () => $verifier);
    app()->instance(ScopeOptions::class, new ScopeOptions('index-install', 'index_control', [], []));
    app()->instance(ControlStoreIsolation::class, new class($control) implements ControlStoreIsolation
    {
        public function __construct(private ConnectionInterface $expected) {}

        public function verify(ConnectionInterface $control, string $deploymentId): void
        {
            if ($control !== $this->expected || $deploymentId !== 'index-install') {
                throw new LogicException('Wrong control store.');
            }
        }
    });
    $ledger = app()->make(DatabaseScopeControl::class);
    app()->instance(ProviderEffectLedger::class, $ledger);
    app()->instance(ScopeAdmission::class, $ledger);
    $ledger->register('index-account', new PhysicalBinding('index-install', 'index-owner', 1, 'incarnation', str_repeat('a', 64)));
    $contract = new MessageContract('equipment.changed', 2, MessageClass::IntegrationEvent, 'axiomasoft/corex-search', PiiClass::Pseudonymous, 'search.index');
    $registry = Mockery::mock(MessageContractRegistry::class);
    $registry->shouldReceive('get')->with('equipment.changed', 2)->andReturn($contract);
    $registry->shouldReceive('get')->with('equipment.schema.changed', 2)->andReturn(
        new MessageContract('equipment.schema.changed', 2, MessageClass::IntegrationEvent, 'axiomasoft/corex-search', PiiClass::Pseudonymous, 'search.index'));
    app()->instance(MessageContractRegistry::class, $registry);

    $migrations = glob(dirname(__DIR__, 2).'/database/migrations/tenant/*.php');
    foreach (array_reverse($migrations) as $file) {
        (require $file)->down();
    }
    foreach ($migrations as $file) {
        (require $file)->up();
    }
    $inbox = glob(dirname(__DIR__, 3).'/core/database/migrations/tenant/*_create_sys_inbox_messages.php')[0];
    (require $inbox)->down();
    (require $inbox)->up();
    $this->source = new class implements DataScopeAuthorizer, FieldUsePolicy, SearchProjectionContributor, SearchSource
    {
        public array $values = ['pump-a' => 'scan-a', 'pump-b' => 'scan-b'];

        public string $witness = 'acl-1';

        public string $revision = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';

        public function page(DataScope $scope, EntityHandle $entity, ?RecordRef $after, int $limit): RecordBatch
        {
            $keys = array_keys($this->values);
            sort($keys, SORT_STRING);
            $keys = array_values(array_filter($keys, static fn (string $key): bool => $after === null || strcmp($key, $after->id) > 0));
            $records = array_map(static fn (string $id): RecordRef => new RecordRef($entity->value, $id), array_slice($keys, 0, $limit));

            return new RecordBatch(new RecordRefs($records), count($keys) > $limit ? end($records) : null);
        }

        public function load(DataScope $scope, RecordRefs $records): SourceRecords
        {
            $rows = [];
            foreach ($records->records as $record) {
                if (isset($this->values[$record->id])) {
                    $rows[] = new SourceRecord($record, hash('sha256', $this->values[$record->id]), new ProjectedValues(['title' => $this->values[$record->id]]));
                }
            }

            return new SourceRecords($rows);
        }

        public function describe(ProjectionContext $context): ProjectionFields
        {
            return new ProjectionFields([new ProjectionField('title', FieldType::Text, [FieldUse::Read, FieldUse::Search])], $this->revision);
        }

        public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues
        {
            return new ProjectedValues([]);
        }

        public function authorize(DataScope $scope, RecordRef $record, FieldSelection $fields, FieldUse $use): DataScopeAccessDecision
        {
            return new DataScopeAccessDecision(true, 'index-service');
        }

        public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
        {
            return FieldUsePlan::unconditional($this->witness);
        }
    };
    foreach ([SearchSource::class, SearchProjectionContributor::class, FieldUsePolicy::class, DataScopeAuthorizer::class] as $contract) {
        app()->instance($contract, $this->source);
    }
    $this->driver = new class implements IndexDriver
    {
        public array $docs = [];

        public array $tasks = [];

        public bool $loseAck = false;

        public bool $unknown = false;

        public bool $pending = false;

        public bool $failed = false;

        public function submit(IndexBatch $batch): IndexReceipt
        {
            expect(DB::connection()->transactionLevel())->toBe(0)
                ->and(DB::table('corex_search_submissions')->where('submission_id', $batch->submissionId)->value('state'))->toBe('sending');
            $this->tasks[$batch->submissionId] = $batch;

            if ($this->loseAck) {
                $this->loseAck = false;

                throw new RuntimeException('Connection closed after acceptance.');
            }

            return new IndexReceipt($batch->submissionId);
        }

        public function lookup(IndexBatch $batch, ?IndexReceipt $receipt): IndexOutcome
        {
            app()->make(IndexLookupAccess::class)->assert($batch);
            $actual = $this->tasks[$receipt?->taskId ?? $batch->submissionId] ?? null;

            if ($this->unknown || $actual?->target->uid !== $batch->target->uid) {
                return IndexOutcome::Unknown;
            }

            if ($this->pending) {
                return IndexOutcome::Pending;
            }

            if ($this->failed) {
                return IndexOutcome::Failed;
            }
            $uid = $batch->target->uid;

            if ($batch->action === IndexAction::Create) {
                $this->docs[$uid] = [];
            } elseif ($batch->action === IndexAction::Drop) {
                unset($this->docs[$uid]);
            } else {
                foreach ($batch->documents as $doc) {
                    if ($batch->action === IndexAction::DeleteRecords) {
                        unset($this->docs[$uid][$doc->record->id]);
                    } else {
                        $this->docs[$uid][$doc->record->id] = $doc->values->values['title'];
                    }
                }
            }

            return IndexOutcome::Confirmed;
        }

        public function canary(IndexTarget $target): bool
        {
            return isset($this->docs[$target->uid]);
        }
    };
    $driver = $this->driver;
    app()->make(SearchDriverRegistry::class)->registerIndex('test', static fn () => $driver);
    $this->runs = app()->make(SearchRebuilds::class);
    $this->worker = app()->make(IndexWorker::class);
    $this->publisher = app()->make(RebuildWorker::class);
    $this->active = app()->make(ActiveGenerations::class);
});

it('holds generation allocation through the caller commit and refuses a previously planned start after Search closes', function (): void {
    $owner = DB::connection();
    config(['database.connections.search_observer' => [...$owner->getConfig(), 'name' => 'search_observer']]);
    $observer = DB::connection('search_observer');
    expect($observer->getPdo())->not->toBe($owner->getPdo());
    $control = app(DatabaseScopeControl::class);
    $search = new ModuleRef('axiomasoft/corex-search');
    $plan = $this->runs->plan($this->scope, new RebuildInput($this->entity));
    $later = $this->runs->plan($this->scope, new RebuildInput($this->entity));
    $key = new OperationKey((string) Str::uuid7(), 1);
    $fence = (string) Str::uuid7();
    $owner->beginTransaction();

    try {
        $started = $this->runs->start($this->scope, $plan->ref, $key);
        $uid = $owner->table('corex_search_generations')->sole()->uid;
        expect($uid)->toBeString()
            ->and($observer->table('corex_search_generations')->count())->toBe(0)
            ->and($control->outstandingReferences($this->scope, $search))->toHaveCount(1);
        $control->fenceModule($this->scope, $search, $fence, null);
        expect($control->outstandingReferences($this->scope, $search))->toHaveCount(1);
        $owner->commit();
    } finally {
        if ($owner->transactionLevel() > 0) {
            $owner->rollBack();
        }
    }
    expect($observer->table('corex_search_generations')->sole()->uid)->toBe($uid)
        ->and($control->outstandingReferences($this->scope, $search))->toBe([])
        ->and($this->runs->start($this->scope, $plan->ref, $key))->toEqual($started);
    $this->runs->cancel($this->scope, $started);
    expect(fn () => $this->runs->plan($this->scope, new RebuildInput($this->entity)))->toThrow(LifecycleBlocked::class);
    $owner->beginTransaction();

    try {
        $this->runs->start($this->scope, $later->ref, new OperationKey((string) Str::uuid7(), 1));
        $this->fail('A previously planned allocation was accepted under the Search fence.');
    } catch (LifecycleBlocked $blocked) {
        expect($blocked->getMessage())->toBe('Module reference admission is closed.');
    } finally {
        $owner->rollBack();
    }
    expect($control->moduleClosure($this->scope, $search))->toBe($fence)
        ->and($observer->table('corex_search_generations')->count())->toBe(1)
        ->and($observer->table('corex_search_rebuilds')->where('rebuild_id', $later->ref->id)->value('state'))->toBe('planned');
    $control->reopenModule($this->scope, $search, $fence);
    $this->runs->start($this->scope, $later->ref, new OperationKey((string) Str::uuid7(), 1));
    expect($observer->table('corex_search_generations')->count())->toBe(2);
});

function indexRebuild(): RebuildRef
{
    $t = test();
    $plan = $t->runs->plan($t->scope, new RebuildInput($t->entity));
    $key = new OperationKey((string) Str::uuid7(), 1);
    $ref = $t->runs->start($t->scope, $plan->ref, $key);
    expect($t->runs->start($t->scope, $plan->ref, $key))->toEqual($ref);

    return $ref;
}

function indexDrain(int $number): void
{
    $t = test();
    for ($n = 0; $n < 100 && $t->worker->advance($t->scope, $t->entity, $number); $n++) {
    }
    expect($n)->toBeLessThan(100);
}

function indexPublish(): RebuildRef
{
    $t = test();
    $ref = indexRebuild();
    indexDrain($t->runs->inspect($t->scope, $ref)->destination);
    $t->publisher->barrier($t->scope, $ref);
    $t->publisher->publish($t->scope, $ref);

    return $ref;
}

function indexEvent(string $id, string $record): MessageEnvelope
{
    return new MessageEnvelope($id, 'urn:equipment', 'equipment.changed', 2, new DateTimeImmutable('2026-01-01Z'), test()->scope,
        new MessagePayload(['record' => $record]), PiiClass::Pseudonymous, new RecordRef('equipment', $record), 'correlation');
}

function indexReceive(MessageEnvelope $message): bool
{
    $db = DB::connection();

    return $db->transaction(function () use ($db, $message): bool {
        if (! app()->make(Inbox::class)->claim($db, new ConsumerId('search.equipment', 1), $message)) {
            return false;
        }
        app()->make(SearchInvalidations::class)->apply($db, $message, test()->entity, [$message->subject]);

        return true;
    });
}

it('writes the subscriber receipt and hole-free lane sequence in one owner transaction', function (): void {
    $event = indexEvent('event-1', 'pump-a');
    expect(fn () => DB::transaction(function () use ($event): void {
        indexReceive($event);

        throw new LogicException('interrupt before commit');
    }))->toThrow(LogicException::class, 'interrupt before commit');
    expect(DB::table('sys_inbox_messages')->count())->toBe(0);
    expect(indexReceive($event))->toBeTrue()->and(indexReceive($event))->toBeFalse()
        ->and(indexReceive(indexEvent('event-2', 'pump-a')))->toBeTrue()
        ->and(DB::table('corex_search_dirty')->orderBy('seq')->pluck('seq')->all())->toBe([1, 2])
        ->and(DB::table('corex_search_lanes')->value('last_allocated_seq'))->toBe(2);
});

it('scans before replay and confirms reread values and tombstones across H and H plus one', function (): void {
    $first = indexPublish();
    $old = $this->active->active($this->scope, $this->entity);
    $ref = indexRebuild();
    $to = $this->runs->inspect($this->scope, $ref)->destination;
    for ($i = 0; $i < 6; $i++) {
        $this->worker->advance($this->scope, $this->entity, $to);
    }
    $this->source->values['pump-a'] = 'during scan';
    indexReceive(indexEvent('before-h', 'pump-a'));
    unset($this->source->values['pump-b']);
    indexReceive(indexEvent('tombstone', 'pump-b'));
    for ($attempt = 0; $attempt < 20 && $this->runs->inspect($this->scope, $ref)->state === 'scanning'; $attempt++) {
        $this->worker->advance($this->scope, $this->entity, $to);
    }
    expect($this->publisher->barrier($this->scope, $ref)->barrier)->toBe(2);
    $this->source->values['pump-c'] = 'after H';
    indexReceive(indexEvent('after-h', 'pump-c'));
    indexDrain($old->generation);
    expect($this->active->active($this->scope, $this->entity)->uid)->toBe($old->uid)
        ->and(fn () => $this->publisher->publish($this->scope, $ref))->toThrow(LogicException::class);
    indexDrain($to);
    $status = $this->publisher->publish($this->scope, $ref);
    $new = $this->active->active($this->scope, $this->entity);
    expect($this->driver->docs[$new->uid])->toBe(['pump-a' => 'during scan'])
        ->and(DB::table('corex_search_lanes')->value('next_seq'))->toBe(3)
        ->and($this->publisher->publish($this->scope, $ref))->toEqual($status);
    indexDrain($to);
    expect($this->driver->docs[$new->uid]['pump-c'])->toBe('after H')
        ->and(DB::table('corex_search_submissions')->where('action', IndexAction::DeleteRecords->value)->where('last_seq', 2)->where('state', 'confirmed')->count())->toBe(1)
        ->and($this->runs->inspect($this->scope, $first)->state)->toBe('published');
});

it('cancels a frozen destination and resumes the old writer at its confirmed prefix', function (): void {
    indexPublish();
    $ref = indexRebuild();
    indexDrain(2);
    indexReceive(indexEvent('one', 'pump-a'));
    $this->publisher->barrier($this->scope, $ref);
    $this->source->values['pump-a'] = 'after freeze';
    indexReceive(indexEvent('two', 'pump-a'));
    expect($this->worker->advance($this->scope, $this->entity, 1))->toBeFalse()
        ->and($this->runs->cancel($this->scope, $ref)->state)->toBe('cancelled');
    indexDrain(1);
    $active = $this->active->active($this->scope, $this->entity);
    expect($active->generation)->toBe(1)->and($this->driver->docs[$active->uid]['pump-a'])->toBe('after freeze')
        ->and(DB::table('corex_search_lanes')->value('next_seq'))->toBe(3);
    indexDrain(2);
    expect(DB::table('corex_search_generations')->where('generation', 2)->value('state'))->toBe('removed');
});

it('recovers a lost ACK by original submission and fences an ambiguous UID permanently', function (): void {
    $ref = indexRebuild();
    $this->driver->loseAck = true;
    indexDrain(1);
    expect($this->runs->inspect($this->scope, $ref)->state)->toBe('catching-up')
        ->and(DB::table('corex_search_submissions')->where('action', 'create')->where('state', 'confirmed')->count())->toBe(1);
    $this->publisher->barrier($this->scope, $ref);
    $this->publisher->publish($this->scope, $ref);
    $next = indexRebuild();
    $this->driver->loseAck = true;
    $this->driver->unknown = true;
    indexDrain(2);
    $unknown = DB::table('corex_search_generations')->where('generation', 2)->first();
    expect($unknown->state)->toBe('quarantined')->and($this->runs->inspect($this->scope, $next)->state)->toBe('failed')
        ->and($this->worker->advance($this->scope, $this->entity, 2))->toBeFalse();
    $this->driver->unknown = false;
    $replacement = indexPublish();
    $target = $this->active->active($this->scope, $this->entity);
    expect($target->generation)->toBe(3)->and($target->uid)->not->toBe($unknown->uid)
        ->and($this->runs->inspect($this->scope, $replacement)->state)->toBe('published');
});

it('rolls the retained generation forward through a new barrier with concurrent dirty admissions', function (): void {
    indexPublish();
    $old = $this->active->active($this->scope, $this->entity);
    $second = indexPublish();
    $this->source->values['pump-a'] = 'rollback value';
    indexReceive(indexEvent('rollback-first', 'pump-a'));
    $rollback = $this->runs->rollback($this->scope, $second, new GenerationRef(2));
    expect($this->runs->rollback($this->scope, $second, new GenerationRef(2)))->toEqual($rollback);
    expect($this->publisher->barrier($this->scope, $rollback)->barrier)->toBe(1);
    unset($this->source->values['pump-b']);
    indexReceive(indexEvent('rollback-after', 'pump-b'));
    indexDrain(1);
    $this->source->witness = 'acl-changed';
    expect(fn () => $this->publisher->publish($this->scope, $rollback))->toThrow(LogicException::class, 'witness');
    $this->source->witness = 'acl-1';
    $status = $this->publisher->publish($this->scope, $rollback);
    expect($status->destination)->toBe(1)->and($this->active->active($this->scope, $this->entity)->uid)->toBe($old->uid)
        ->and($this->driver->docs[$old->uid]['pump-b'])->toBe('scan-b');
    indexDrain(1);
    expect($this->driver->docs[$old->uid])->toBe(['pump-a' => 'rollback value'])
        ->and(DB::table('corex_search_generations')->where('state', 'active')->count())->toBe(1);
});

it('keeps a single unresolved task and withholds the watermark until its receipt confirms', function (): void {
    indexPublish();
    indexReceive(indexEvent('pending', 'pump-a'));
    $this->driver->pending = true;
    indexDrain(1);
    indexReceive(indexEvent('later', 'pump-b'));
    indexDrain(1);
    expect(DB::table('corex_search_generations')->value('confirmed_seq'))->toBe(0)
        ->and(DB::table('corex_search_submissions')->where('state', 'accepted')->count())->toBe(1)
        ->and(DB::table('corex_search_submissions')->where('first_seq', 2)->count())->toBe(0);
    $this->driver->pending = false;
    indexDrain(1);
    expect(DB::table('corex_search_generations')->value('confirmed_seq'))->toBe(2)
        ->and(DB::connection('index_control')->table('corex_scope_tickets')->where('provider_owner', 'corex.search.index')->where('state', 'outstanding')->count())->toBe(0);
});

it('isolates a delayed old task from the new active UID and the next restore epoch', function (): void {
    indexPublish();
    $old = $this->active->active($this->scope, $this->entity);
    $this->source->values['pump-a'] = 'old in flight';
    indexReceive(indexEvent('old-write', 'pump-a'));
    $this->worker->advance($this->scope, $this->entity, 1);
    $this->worker->advance($this->scope, $this->entity, 1);
    $this->source->values['pump-a'] = 'cutover value';
    indexPublish();
    $new = $this->active->active($this->scope, $this->entity);
    indexDrain(1);
    expect($this->driver->docs[$old->uid]['pump-a'])->toBe('old in flight')
        ->and($this->driver->docs[$new->uid]['pump-a'])->toBe('cutover value')
        ->and($new->documentId(new RecordRef('equipment', 'pump-a')))->not->toBe($old->documentId(new RecordRef('equipment', 'pump-a')));
    $s = $this->scope;
    $restored = new DataScope($s->deploymentId, $s->accountId, null, $s->bindingId, $s->bindingGeneration, $s->accountIncarnation,
        str_repeat('b', 64), 'service', $s->principalId);
    expect(fn () => $this->active->active($restored, $this->entity))->toThrow(ScopeContextMismatch::class)
        ->and(IndexTarget::uidFor($restored, $this->entity, $new->backend, $new->cluster, $new->generation, $new->allocationId))->not->toEqual($new->uid);
});

it('rolls back a precommit interruption and inspects exactly one publication after loss of its response', function (): void {
    indexPublish();
    $ref = indexRebuild();
    indexDrain(2);
    $this->publisher->barrier($this->scope, $ref);
    $armed = true;
    DB::listen(function (QueryExecuted $event) use (&$armed): void {
        if ($armed && str_starts_with($event->sql, 'update "corex_search_lanes" set "active_generation"')) {
            $armed = false;

            throw new RuntimeException('interrupted before publication commit');
        }
    });
    expect(fn () => $this->publisher->publish($this->scope, $ref))->toThrow(RuntimeException::class, 'before publication')
        ->and($this->runs->inspect($this->scope, $ref)->state)->toBe('barrier')
        ->and($this->active->active($this->scope, $this->entity)->generation)->toBe(1);
    $lostResponse = false;
    app('events')->listen(TransactionCommitted::class,
        function (TransactionCommitted $event) use (&$lostResponse): void {
            if (! $lostResponse && $event->connection === DB::connection() && DB::table('corex_search_lanes')->value('active_generation') === 2) {
                $lostResponse = true;

                throw new RuntimeException('interrupted after publication commit');
            }
        });
    expect(fn () => $this->publisher->publish($this->scope, $ref))->toThrow(RuntimeException::class, 'after publication')
        ->and($this->runs->inspect($this->scope, $ref)->state)->toBe('published')
        ->and($this->publisher->publish($this->scope, $ref)->destination)->toBe(2)
        ->and(DB::table('corex_search_generations')->where('state', 'active')->count())->toBe(1);
});

it('refuses stale generation claims and retains old UIDs until the rollback window closes', function (): void {
    indexPublish();
    $second = indexPublish();
    expect(fn () => $this->runs->rollback($this->scope, $second, new GenerationRef(1)))->toThrow(LogicException::class);
    expect($this->worker->advance($this->scope, $this->entity, 1))->toBeFalse();
    DB::table('corex_search_generations')->where('generation', 1)->update(['retain_until' => '2000-01-01T00:00:00Z']);
    expect(fn () => $this->runs->rollback($this->scope, $second, new GenerationRef(2)))->toThrow(LogicException::class, 'full rebuild');
    indexDrain(1);
    expect(DB::table('corex_search_generations')->where('generation', 1)->value('state'))->toBe('removed')
        ->and($this->active->active($this->scope, $this->entity)->generation)->toBe(2);
});

it('recovers independent control settlement after the owner committed the confirmed prefix', function (): void {
    indexPublish();
    indexReceive(indexEvent('settlement', 'pump-a'));
    $this->worker->advance($this->scope, $this->entity, 1);
    $this->worker->advance($this->scope, $this->entity, 1);
    $row = DB::table('corex_search_submissions')->where('first_seq', 1)->first();
    $armed = true;
    app('events')->listen(TransactionCommitted::class, function ($event) use (&$armed, $row): void {
        if ($armed && $event->connection === DB::connection() && DB::table('corex_search_submissions')->where('submission_id', $row->submission_id)->value('state') === 'confirmed') {
            $armed = false;

            throw new RuntimeException('interrupted after owner receipt');
        }
    });
    expect(fn () => $this->worker->advance($this->scope, $this->entity, 1))->toThrow(RuntimeException::class, 'after owner receipt');
    $ticket = DB::connection('index_control')->table('corex_scope_tickets')->where('ticket_id', $row->provider_ticket);
    expect($ticket->value('state'))->toBe('outstanding')->and(DB::table('corex_search_generations')->value('confirmed_seq'))->toBe(1);
    indexDrain(1);
    expect($ticket->value('state'))->toBe('reconciled')
        ->and(DB::table('corex_search_submissions')->where('first_seq', 1)->count())->toBe(1)
        ->and(DB::table('corex_search_submissions')->where('first_seq', 1)->value('control_recorded'))->toBeTrue();
});

it('drains an exact provider receipt while closing with all ordinary index work fenced', function (bool $ownerAlreadyConfirmed): void {
    indexPublish();
    indexReceive(indexEvent('closing', 'pump-a'));
    $this->worker->advance($this->scope, $this->entity, 1);
    $this->worker->advance($this->scope, $this->entity, 1);
    $batch = DB::table('corex_search_submissions')->where('first_seq', 1)->first();

    if ($ownerAlreadyConfirmed) {
        $armed = true;
        app('events')->listen(TransactionCommitted::class, function ($event) use (&$armed, $batch): void {
            if ($armed && $event->connection === DB::connection() && DB::table('corex_search_submissions')->where('submission_id', $batch->submission_id)->value('state') === 'confirmed') {
                $armed = false;

                throw new RuntimeException('owner committed without control ACK');
            }
        });
        expect(fn () => $this->worker->advance($this->scope, $this->entity, 1))->toThrow(RuntimeException::class, 'without control ACK');
    }
    $receipt = DB::connection('index_control')->table('corex_scope_tickets')->where('ticket_id', $batch->provider_ticket)->first();
    $s = $this->scope;
    $effect = new ProviderEffectRef($s->accountId, $s->accountIncarnation, $s->workspaceId, $receipt->provider_owner, $receipt->provider_namespace, $receipt->effect_key);
    $control = app()->make(DatabaseScopeControl::class);
    $closing = $control->close($s->accountId, 1);
    expect(fn () => $this->worker->advance($s, $this->entity, 1))->toThrow(ScopeContextMismatch::class)
        ->and(fn () => $control->drain($s->accountId, $closing))->toThrow(ScopeContextMismatch::class)
        ->and(fn () => app()->make(ProviderEffectRecovery::class)->recover($effect))->toThrow(ScopeContextMismatch::class);
    app()->instance(ProviderRecoveryAuthority::class, new class($effect) implements ProviderRecoveryAuthority
    {
        public function __construct(private ProviderEffectRef $expected) {}

        public function authorize(ProviderEffectRef $effect): void
        {
            if ($effect->identity() !== $this->expected->identity()) {
                throw new ScopeContextMismatch('Recovery credential cannot authorize another submission.');
            }
        }
    });
    expect(fn () => app()->make(IndexLookupAccess::class)->assert($this->driver->tasks[$batch->submission_id]))->toThrow(ScopeContextMismatch::class)
        ->and(app()->make(ProviderEffectRecovery::class)->recover($effect))->toBeTrue()
        ->and(DB::table('corex_search_generations')->value('confirmed_seq'))->toBe(1)
        ->and($control->inspect($effect)->state)->toBe('reconciled')
        ->and($control->drain($s->accountId, $closing))->toBe(3);
})->with(['accepted remote task' => [false], 'confirmed local receipt' => [true]]);

it('rereads a planned projection before the provider send and keeps its sequence pending', function (): void {
    indexPublish();
    indexReceive(indexEvent('planned-change', 'pump-a'));
    $this->worker->advance($this->scope, $this->entity, 1);
    $this->source->values['pump-a'] = 'updated between plan and send';
    expect($this->worker->advance($this->scope, $this->entity, 1))->toBeTrue()
        ->and(DB::table('corex_search_submissions')->where('first_seq', 1)->value('state'))->toBe('cancelled')
        ->and(DB::table('corex_search_generations')->value('confirmed_seq'))->toBe(0);
    indexDrain(1);
    $active = $this->active->active($this->scope, $this->entity);
    expect($this->driver->docs[$active->uid]['pump-a'])->toBe('updated between plan and send')
        ->and(DB::table('corex_search_submissions')->where('first_seq', 1)->where('state', 'confirmed')->count())->toBe(1);
});

it('preserves the rollback target and drains its outstanding claim before retention cleanup', function (string $claim): void {
    indexPublish();
    $old = $this->active->active($this->scope, $this->entity);
    $second = indexPublish();
    $active = $this->active->active($this->scope, $this->entity);
    $retained = DB::table('corex_search_generations')->where('generation', $old->generation);
    $until = $retained->value('retain_until');
    $lane = DB::table('corex_search_lanes');
    $submissions = DB::table('corex_search_submissions')->where('generation', $old->generation);

    indexReceive(indexEvent('rollback-before-barrier', 'pump-a'));
    $rollback = $this->runs->rollback($this->scope, $second, new GenerationRef($active->generation));
    expect($this->publisher->barrier($this->scope, $rollback)->barrier)->toBe(1);
    indexReceive(indexEvent('rollback-after-barrier', 'pump-b'));
    $this->worker->advance($this->scope, $this->entity, $old->generation);

    if ($claim !== 'planned') {
        $this->worker->advance($this->scope, $this->entity, $old->generation);
    }
    $entry = (clone $submissions)->where('first_seq', 1);
    expect($entry->value('state'))->toBe($claim === 'planned' ? 'planned' : 'accepted');
    $cancel = $this->runs->cancel($this->scope, $rollback);
    expect($cancel->state)->toBe('cancelled')->and($cancel->barrier)->toBe(1)
        ->and($retained->value('state'))->toBe('retained')->and($retained->value('retain_until'))->toBe($until)
        ->and($lane->value('active_generation'))->toBe($active->generation)->and($lane->value('writer_generation'))->toBe($active->generation)
        ->and($lane->value('freeze_seq'))->toBeNull()->and($lane->value('last_allocated_seq'))->toBe(2)->and($lane->value('next_seq'))->toBe(1);

    $this->driver->pending = true;
    indexDrain($old->generation);
    expect($entry->value('state'))->toBe($claim === 'planned' ? 'cancelled' : 'accepted')
        ->and($retained->value('confirmed_seq'))->toBe(0)->and($retained->value('state'))->toBe('retained')
        ->and(array_key_exists($old->uid, $this->driver->docs))->toBeTrue()
        ->and((clone $submissions)->where('action', IndexAction::Drop->value)->count())->toBe(0);

    $this->driver->pending = false;
    indexDrain($active->generation);
    expect($lane->value('next_seq'))->toBe(3);
    $this->clock->advance($this->clock->now()->diff((new DateTimeImmutable($until))->modify('+1 second')));

    if ($claim !== 'planned') {
        $this->driver->pending = true;
        expect($this->worker->advance($this->scope, $this->entity, $old->generation))->toBeFalse()
            ->and($retained->value('state'))->toBe('retained')->and(array_key_exists($old->uid, $this->driver->docs))->toBeTrue();
        $this->driver->pending = false;
        $this->driver->failed = $claim === 'failed';
        $this->worker->advance($this->scope, $this->entity, $old->generation);
        $this->driver->failed = false;
        expect($entry->value('state'))->toBe($claim === 'failed' ? 'failed' : 'confirmed')
            ->and($retained->value('confirmed_seq'))->toBe($claim === 'failed' ? 0 : 1)
            ->and($lane->value('next_seq'))->toBe(3);
    }
    indexDrain($old->generation);
    expect($retained->value('state'))->toBe('removed')->and(array_key_exists($old->uid, $this->driver->docs))->toBeFalse()
        ->and((clone $submissions)->where('action', IndexAction::Drop->value)->where('state', 'confirmed')->count())->toBe(1)
        ->and($this->active->active($this->scope, $this->entity)->uid)->toBe($active->uid);
})->with(['planned claim' => ['planned'], 'accepted and confirmed' => ['confirmed'], 'accepted and failed' => ['failed']]);

it('fences an older rebuild at publication and settles requests only for a current mapping', function (): void {
    $old = indexPublish();
    $stale = indexRebuild();
    $destination = $this->runs->inspect($this->scope, $stale)->destination;
    indexDrain($destination);
    $this->publisher->barrier($this->scope, $stale);

    $request = function (string $messageId, string $revision): bool {
        $db = DB::connection();
        $fact = new MessageEnvelope($messageId, 'urn:equipment', 'equipment.schema.changed', 2, new DateTimeImmutable('2026-01-01Z'), $this->scope,
            new MessagePayload(['target' => $revision]), PiiClass::Pseudonymous, $this->entity, 'correlation');

        return $db->transaction(function () use ($db, $fact, $revision): bool {
            if (! app()->make(Inbox::class)->claim($db, new ConsumerId('search.equipment.schema', 2), $fact)) {
                return false;
            }
            app()->make(SearchMappingInvalidations::class)->apply($db, $fact, $this->entity, $revision, $revision);

            return true;
        });
    };
    $requests = DB::table('corex_search_rebuilds')->whereNotNull('source_revision');
    $signals = app()->make(SearchMappingInvalidations::class);
    expect($signals->pending($this->scope, $this->entity))->toBeFalse();
    $this->source->revision = $second = str_repeat('e', 64);
    expect($request('schema-2', $second))->toBeTrue()
        ->and($request('schema-2', $second))->toBeFalse()
        ->and($signals->pending($this->scope, $this->entity))->toBeTrue()
        ->and((clone $requests)->where('state', 'requested')->sole()->mapping_digest)->toBe($second);

    $this->source->revision = $third = str_repeat('f', 64);
    expect($request('schema-3', $third))->toBeTrue()
        ->and((clone $requests)->where('state', 'requested')->sole()->mapping_digest)->toBe($third)
        ->and((clone $requests)->where('state', 'superseded')->sole()->mapping_digest)->toBe($second)
        ->and(DB::table('corex_search_dirty')->count())->toBe(0);

    expect(fn () => $this->publisher->publish($this->scope, $stale))->toThrow(LogicException::class, 'Search mapping or policy witness has changed.');
    expect($this->runs->inspect($this->scope, $stale)->state)->toBe('barrier')
        ->and($this->active->active($this->scope, $this->entity)->generation)->toBe($this->runs->inspect($this->scope, $old)->destination);
    expect($this->runs->cancel($this->scope, $stale)->state)->toBe('cancelled');
    $current = indexPublish();
    expect($this->runs->inspect($this->scope, $current)->state)->toBe('published')
        ->and($this->active->active($this->scope, $this->entity)->mappingDigest)->toBe($third)
        ->and($signals->pending($this->scope, $this->entity))->toBeFalse()
        ->and((clone $requests)->where('state', 'satisfied')->sole()->mapping_digest)->toBe($third)
        ->and((clone $requests)->where('state', 'requested')->count())->toBe(0);

    expect($request('schema-late', $second))->toBeTrue()
        ->and((clone $requests)->where('state', 'satisfied')->count())->toBe(2)
        ->and((clone $requests)->where('state', 'requested')->count())->toBe(0)
        ->and(DB::table('corex_search_dirty')->count())->toBe(0);
});
