<?php

declare(strict_types=1);

use CoreX\Audit\Contracts\Auditable;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Contracts\AuditRetention;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Audit\Enums\ActorType;
use CoreX\Audit\Internal\AuditLog;
use CoreX\Audit\Internal\ModuleAuditHook;
use CoreX\Audit\Internal\NullAuditRetention;
use CoreX\Audit\Internal\QueuedAuditLogger;
use CoreX\Audit\Internal\RecordAuditEntry;
use CoreX\Audit\Laravel\HasAuditLog;
use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Tests\Stubs\StubAuditableThing;
use CoreX\Tests\Stubs\Widget;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Enums\Entities\Capability;
use Packman\Modules\Testing\ArrayEntityRegistry;

uses()->group('pg');

const AUDIT_TEST_CONNECTION = 'pgsql';

/**
 * HasAuditLog model with a non-empty $fillable: secret is fillable-but-sensitive
 * (masked), password/remember_token are guarded (absent from the diff entirely).
 */
final class AuditSensitiveStub extends Model implements Auditable
{
    use HasAuditLog;
    use HasUuids;

    protected $table = 'audit_sensitive_stubs';

    protected $fillable = ['name', 'secret'];

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at'];
    }

    public function auditSensitive(): array
    {
        return ['secret'];
    }
}

/**
 * $guarded = [] model (the pattern both Widget and StubAuditableThing already
 * use elsewhere in this suite) — every attribute is mass-assignable, so
 * `isFillable()` alone cannot keep password/remember_token out of the diff.
 * Audit regression: AuditObserver must not
 * rely solely on isFillable() to decide what is audit-safe.
 */
final class AuditUnguardedStub extends Model implements Auditable
{
    use HasAuditLog;
    use HasUuids;

    protected $table = 'audit_unguarded_stubs';

    protected $guarded = [];

    public function auditExcept(): array
    {
        return ['created_at', 'updated_at'];
    }

    public function auditSensitive(): array
    {
        return [];
    }
}

/** AuditRetention with a fixed cutoff, for the prune test. */
final readonly class FixedAuditRetention implements AuditRetention
{
    public function __construct(private ?DateTimeImmutable $cutoff) {}

    public function olderThan(): ?DateTimeImmutable
    {
        return $this->cutoff;
    }
}

function auditPg(): Connection
{
    return DB::connection(AUDIT_TEST_CONNECTION);
}

function auditLogger(): QueuedAuditLogger
{
    return app()->make(QueuedAuditLogger::class);
}

/** @param array<string, mixed> $overrides */
function auditEntityFor(string $model, string $table, string $handle, array $overrides = []): EntityDefinition
{
    return new EntityDefinition(
        handle: $handle,
        model: $model,
        table: $table,
        module: 'stub',
        labelSingular: 'Thing',
        labelPlural: 'Things',
        capabilities: $overrides['capabilities'] ?? [Capability::Auditable],
        fields: [],
        titleAttribute: null,
    );
}

beforeEach(function (): void {
    // Full randomized runs may already enforce the owning module morph map.
    // These audit-only models must participate rather than relax enforcement.
    $entities = new ArrayEntityRegistry;
    foreach ([Widget::class => ['widgets', 'stub.widget'], StubAuditableThing::class => ['stub_auditable_things', 'stub.auditable_thing'], AuditSensitiveStub::class => ['audit_sensitive_stubs', 'audit.sensitive'], AuditUnguardedStub::class => ['audit_unguarded_stubs', 'audit.unguarded']] as $model => [$table, $handle]) {
        $entities->register(auditEntityFor($model, $table, $handle));
    }
    Relation::enforceMorphMap(array_column($entities->all(), 'model', 'handle'));
    // CoreXTestCase disables audit by default (missing-table guard for the
    // sqlite lane, see its defineEnvironment) — this suite opts back in.
    config(['corex.audit.enabled' => true]);
    app()->forgetInstance(CoreOptions::class);

    // sys_audit_log is PG-only (jsonb/CHECK/BRIN). Reset to a clean
    // public schema so each test starts with no migration history (mirrors
    // SettingsTest's/ModuleLifecycleTest's pg-lane pattern).
    auditPg()->statement('DROP SCHEMA IF EXISTS public CASCADE');
    auditPg()->statement('CREATE SCHEMA public');
    DB::purge(AUDIT_TEST_CONNECTION);

    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], AUDIT_TEST_CONNECTION);
    DB::setDefaultConnection(AUDIT_TEST_CONNECTION);
    Schema::clearResolvedInstance('db.schema');
    $widgetsMigration = require dirname(__DIR__).'/database/migrations/2026_06_14_100000_create_widgets_table.php';
    $widgetsMigration->up();
    $contexts = LocalWorkContexts::install($this->app);
    $contexts->initialize(new AccountRef('00000000-0000-7000-8000-0000000000aa', 'acct', 'active', [], []));
    $this->app->instance(LocalWorkContexts::class, $contexts);
    WorkScopeFixture::install($this->app, 'audit-tests', 'service')->register($contexts->current());

    $this->app->instance(AuditLogger::class, auditLogger());
});

afterEach(function (): void {
    DB::setDefaultConnection('testbench');
    Schema::clearResolvedInstance('db.schema');
    Date::setTestNow();
});

it('enqueues on the sync path instead of writing synchronously', function (): void {
    Queue::fake();

    auditLogger()->record(new AuditEntry(
        action: 'created',
        actorType: ActorType::System,
        entityType: 'stub.thing',
        entityId: '00000000-0000-0000-0000-000000000001',
    ));

    Queue::assertPushed(RecordAuditEntry::class);
    expect(AuditLog::on(AUDIT_TEST_CONNECTION)->count())->toBe(0);
});

it('HasAuditLog trait records an append-only entry on create through the queue worker', function (): void {
    $widget = Widget::create(['name' => 'one']);

    $entry = AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'created')->sole();

    expect($entry->entity_id)->toBe($widget->id)
        ->and($entry->actor_type)->toBe(ActorType::System)
        ->and($entry->changes)->toHaveKey('name')
        ->and($entry->changes['name']['new'])->toBe('one');
});

it('HasAuditLog trait records the diff on update', function (): void {
    $widget = Widget::create(['name' => 'one']);
    $widget->update(['name' => 'two']);

    $entry = AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'updated')->sole();

    // toEqual, not toBe: jsonb does not preserve key insertion order on
    // round-trip, only key/value pairs.
    expect($entry->changes['name'])->toEqual(['old' => 'one', 'new' => 'two']);
});

it('auto-observer records byCapability(Auditable) with sensitive masking', function (): void {
    Schema::create('stub_auditable_things', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('secret')->nullable();
        $table->timestamps();
    });

    $registry = new ArrayEntityRegistry;
    $registry->register(auditEntityFor(StubAuditableThing::class, 'stub_auditable_things', 'stub.auditable_thing'));

    (new ModuleAuditHook)->wire($registry);

    $thing = StubAuditableThing::create(['name' => 'alpha', 'secret' => 'top-secret']);

    $entry = AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'created')->sole();

    expect($entry->entity_id)->toBe((string) $thing->id)
        ->and($entry->changes['name']['new'])->toBe('alpha')
        ->and($entry->changes['secret']['new'])->toBe('••••••');
});

it('auto-observer skips models that already opt in via HasAuditLog (no double record)', function (): void {
    $registry = new ArrayEntityRegistry;
    $registry->register(auditEntityFor(Widget::class, 'widgets', 'stub.widget'));

    (new ModuleAuditHook)->wire($registry);

    Widget::create(['name' => 'one']);

    expect(AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'created')->count())->toBe(1);
});

it('actor_type falls back to system when unauthenticated', function (): void {
    Schema::create('stub_auditable_things', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('secret')->nullable();
        $table->timestamps();
    });

    $registry = new ArrayEntityRegistry;
    $registry->register(auditEntityFor(StubAuditableThing::class, 'stub_auditable_things', 'stub.auditable_thing'));

    (new ModuleAuditHook)->wire($registry);

    StubAuditableThing::create(['name' => 'alpha']);

    $entry = AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'created')->sole();

    expect($entry->actor_type)->toBe(ActorType::System)
        ->and($entry->actor_id)->toBeNull();
});

it('omits guarded/non-fillable attributes from the diff on create ', function (): void {
    Schema::create('audit_sensitive_stubs', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('secret')->nullable();
        $table->string('password')->nullable();
        $table->string('remember_token')->nullable();
        $table->timestamps();
    });

    // forceFill so the guarded attributes are actually SET on the model —
    // create() would silently drop them (that is the point: they must never
    // reach the audit diff even when present as raw attributes).
    $model = (new AuditSensitiveStub)->forceFill([
        'name' => 'bob',
        'secret' => 's3cret',
        'password' => 'hashed-password',
        'remember_token' => 'a-token',
    ]);
    $model->save();

    $entry = AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'created')->sole();

    expect($entry->changes)->toHaveKey('name')
        ->and($entry->changes['name']['new'])->toBe('bob')
        // Guarded fields are ABSENT (not masked) .
        ->and($entry->changes)->not->toHaveKey('password')
        ->and($entry->changes)->not->toHaveKey('remember_token')
        ->and($entry->changes)->not->toHaveKey('id')
        // Fillable-but-sensitive field is masked, not omitted.
        ->and($entry->changes['secret']['new'])->toBe('••••••');
});

it('excludes password/remember_token even when $guarded = [] makes them fillable ', function (): void {
    Schema::create('audit_unguarded_stubs', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('password')->nullable();
        $table->string('remember_token')->nullable();
        $table->timestamps();
    });

    // $guarded = [] means isFillable() returns true for EVERY attribute — the
    // exclusion must not depend on that check alone (ALWAYS_EXCLUDED).
    $model = AuditUnguardedStub::create([
        'name' => 'carol',
        'password' => 'hashed-password',
        'remember_token' => 'a-token',
    ]);

    $created = AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'created')->sole();

    expect($created->changes)->toHaveKey('name')
        ->and($created->changes)->not->toHaveKey('password')
        ->and($created->changes)->not->toHaveKey('remember_token');

    $model->update(['password' => 'new-hashed-password']);

    $updated = AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'updated')->sole();

    expect($updated->changes)->not->toHaveKey('password');
});

it('masks a fillable sensitive field in both old and new on update ', function (): void {
    Schema::create('audit_sensitive_stubs', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('secret')->nullable();
        $table->string('password')->nullable();
        $table->string('remember_token')->nullable();
        $table->timestamps();
    });

    $model = AuditSensitiveStub::create(['name' => 'bob', 'secret' => 'old-secret']);
    $model->update(['secret' => 'new-secret']);

    $entry = AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'updated')->sole();

    expect($entry->changes['secret'])->toEqual(['old' => '••••••', 'new' => '••••••']);
});

it('killswitch silences the capability path, not only the trait ', function (): void {
    config(['corex.audit.enabled' => false]);
    app()->forgetInstance(CoreOptions::class);

    Schema::create('stub_auditable_things', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('secret')->nullable();
        $table->timestamps();
    });

    $registry = new ArrayEntityRegistry;
    $registry->register(auditEntityFor(StubAuditableThing::class, 'stub_auditable_things', 'stub.auditable_thing'));

    (new ModuleAuditHook)->wire($registry);

    StubAuditableThing::create(['name' => 'alpha', 'secret' => 'top-secret']);

    expect(AuditLog::on(AUDIT_TEST_CONNECTION)->count())->toBe(0);
});

it('killswitch silences a direct AuditLogger::record() at the write seam, not only the observer (defense-in-depth)', function (): void {
    config(['corex.audit.enabled' => false]);
    app()->forgetInstance(CoreOptions::class);

    // Bypass the observer entirely — call the AuditLogger contract directly, as
    // any caller resolving it could. app(AuditObserver::class)->record()'s guard does
    // not cover this path; the write seam must refuse on its own. Sync queue in
    // this suite means an un-guarded record() would write the row synchronously.
    auditLogger()->record(new AuditEntry(
        action: 'created',
        actorType: ActorType::System,
        entityType: 'stub.thing',
        entityId: '00000000-0000-0000-0000-000000000009',
    ));

    expect(AuditLog::on(AUDIT_TEST_CONNECTION)->count())->toBe(0);
});

it('killswitch aborts in the observer itself — an unconditional logger stays untouched (observer isolation)', function (): void {
    config(['corex.audit.enabled' => false]);
    app()->forgetInstance(CoreOptions::class);

    Schema::create('stub_auditable_things', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('secret')->nullable();
        $table->timestamps();
    });

    // Unconditional spy: records EVERY call, with NO killswitch of its own. This
    // isolates AuditObserver's guard from QueuedAuditLogger's write-seam guard
    // (killswitch-writeseam pins the latter separately). The existing
    // 'killswitch silences' tests assert AuditLog stays empty — but that holds
    // even if the observer forgets to guard, because the real logger's write-seam
    // catches it. Only a logger with no guard of its own can prove the observer
    // short-circuits on its own: if it doesn't, this spy captures the call.
    $spy = new class implements AuditLogger
    {
        public int $calls = 0;

        public function record(AuditEntry $entry): void
        {
            $this->calls++;
        }
    };
    $this->app->instance(AuditLogger::class, $spy);

    $registry = new ArrayEntityRegistry;
    $registry->register(auditEntityFor(StubAuditableThing::class, 'stub_auditable_things', 'stub.auditable_thing'));

    (new ModuleAuditHook)->wire($registry);

    StubAuditableThing::create(['name' => 'alpha', 'secret' => 'top-secret']);

    // The observer must short-circuit at its OWN killswitch (AuditObserver::record),
    // never reaching app(AuditLogger::class). Mutating that guard (&& false) lets
    // it through → the spy captures → this reddens.
    expect($spy->calls)->toBe(0);
});

it('snapshots occurred_at and workspace_id at enqueue, not in the worker ', function (): void {
    config([
        'queue.connections.database.connection' => 'work-scope-control',
        'queue.default' => 'database',
    ]);
    Schema::connection('work-scope-control')->create('jobs', static function (Blueprint $table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    $contexts = resolve(LocalWorkContexts::class);
    $eventWorkspace = new WorkspaceRef((string) Str::uuid7(), 'event', 'team', 'event', null);
    $workerWorkspace = new WorkspaceRef((string) Str::uuid7(), 'worker', 'team', 'worker', null);
    $contexts->setWorkspace($eventWorkspace);

    Date::setTestNow('2026-07-14 10:00:00');

    auditLogger()->record(new AuditEntry(
        action: 'created',
        actorType: ActorType::System,
        entityType: 'stub.thing',
        entityId: (string) Str::uuid7(),
    ));

    $queue = Queue::connection('database');
    expect($queue->size())->toBe(1)
        ->and(AuditLog::on(AUDIT_TEST_CONNECTION)->count())->toBe(0);

    $contexts->setWorkspace($workerWorkspace);
    Date::setTestNow('2026-07-14 12:00:00');

    $job = $queue->pop();
    expect($job)->not->toBeNull();
    resolve('queue.worker')->process('database', $job, new WorkerOptions(maxTries: 1));

    $row = AuditLog::on(AUDIT_TEST_CONNECTION)->sole();

    expect($row->workspace_id)->toBe($eventWorkspace->id)
        ->and($row->occurred_at->format('Y-m-d H:i:s'))->toBe('2026-07-14 10:00:00')
        ->and($contexts->current()->workspace)->toBe($workerWorkspace)
        ->and($queue->size())->toBe(0);
});

it('refuses an audit write under a different workspace without queue restoration', function (): void {
    $contexts = resolve(LocalWorkContexts::class);
    $eventWorkspace = new WorkspaceRef((string) Str::uuid7(), 'event', 'team', 'event', null);
    $workerWorkspace = new WorkspaceRef((string) Str::uuid7(), 'worker', 'team', 'worker', null);
    $contexts->setWorkspace($eventWorkspace);
    $scope = resolve(WorkScopeFixture::class)->scope();
    $job = new RecordAuditEntry(new AuditEntry(action: 'created', actorType: ActorType::System), $scope);
    $contexts->setWorkspace($workerWorkspace);

    expect(fn () => app()->call($job->handle(...)))->toThrow(ScopeContextMismatch::class)
        ->and(AuditLog::on(AUDIT_TEST_CONNECTION)->count())->toBe(0)
        ->and($contexts->current()->workspace)->toBe($workerWorkspace);
});

it('records restored and forceDeleted, folding the redundant delete of a forceDelete ', function (): void {
    $widget = Widget::create(['name' => 'x']);
    $widget->delete();
    $widget->restore();
    $widget->forceDelete();

    $actions = AuditLog::on(AUDIT_TEST_CONNECTION)
        ->where('entity_id', $widget->id)
        ->orderBy('seq')
        ->pluck('action')
        ->all();

    // restored and forceDeleted are both recorded. forceDelete() fires 'deleted'
    // AND 'forceDeleted' — the redundant 'deleted' is folded, so the single
    // 'deleted' seen here is the soft delete only (without the fold it would be 2).
    expect($actions)->toContain('restored')
        ->and($actions)->toContain('forceDeleted')
        ->and(collect($actions)->filter(static fn (string $a): bool => $a === 'deleted')->count())->toBe(1)
        ->and(collect($actions)->filter(static fn (string $a): bool => $a === 'forceDeleted')->count())->toBe(1);
});

it('paginates the audit journal by the monotonic seq cursor in insertion order', function (): void {
    // entity_ids are deliberately NOT in sorted order: the keyset must return
    // rows in INSERTION order (what the clock-free bigserial seq encodes), not
    // sorted-by-id order. The old id-cursor test held for any id type —
    // this one fails if ordering falls back to any non-insertion column.
    $entityIds = [
        '00000000-0000-7000-8000-0000000000c1', // inserted 1st
        '00000000-0000-7000-8000-0000000000a2', // inserted 2nd
        '00000000-0000-7000-8000-0000000000b3', // inserted 3rd
    ];

    foreach ($entityIds as $entityId) {
        auditLogger()->record(new AuditEntry(
            action: 'created',
            actorType: ActorType::System,
            entityType: 'stub.thing',
            entityId: $entityId,
        ));
    }

    $ordered = AuditLog::on(AUDIT_TEST_CONNECTION)->orderBy('seq')->get(['seq', 'entity_id']);
    $seqs = $ordered->pluck('seq')->map(static fn (mixed $s): int => (int) $s)->all();

    expect($ordered->pluck('entity_id')->all())->toBe($entityIds)
        ->and($seqs[0])->toBeLessThan($seqs[1])
        ->and($seqs[1])->toBeLessThan($seqs[2]);

    $after = AuditLog::on(AUDIT_TEST_CONNECTION)
        ->where('seq', '>', $seqs[0])
        ->orderBy('seq')
        ->pluck('entity_id')
        ->all();

    expect($after)->toBe([$entityIds[1], $entityIds[2]]);
});

it('corex:audit:prune deletes rows older than the retention boundary and keeps fresh ones ', function (): void {
    Date::setTestNow('2026-06-01 00:00:00');
    auditLogger()->record(new AuditEntry(
        action: 'created',
        actorType: ActorType::System,
        entityType: 'stub.thing',
        entityId: (string) Str::uuid7(),
    ));

    Date::setTestNow('2026-07-14 00:00:00');
    auditLogger()->record(new AuditEntry(
        action: 'created',
        actorType: ActorType::System,
        entityType: 'stub.thing',
        entityId: (string) Str::uuid7(),
    ));

    Date::setTestNow();

    expect(AuditLog::on(AUDIT_TEST_CONNECTION)->count())->toBe(2);

    $this->app->instance(AuditRetention::class, new FixedAuditRetention(new DateTimeImmutable('2026-07-01 00:00:00')));

    $this->artisan('corex:audit:prune', ['--database' => AUDIT_TEST_CONNECTION])->assertSuccessful();

    expect(AuditLog::on(AUDIT_TEST_CONNECTION)->count())->toBe(1)
        ->and(AuditLog::on(AUDIT_TEST_CONNECTION)->sole()->occurred_at->format('Y-m-d'))->toBe('2026-07-14');
});

it('corex:audit:prune with the default NullAuditRetention deletes nothing', function (): void {
    auditLogger()->record(new AuditEntry(
        action: 'created',
        actorType: ActorType::System,
        entityType: 'stub.thing',
        entityId: (string) Str::uuid7(),
    ));

    expect(resolve(AuditRetention::class))->toBeInstanceOf(NullAuditRetention::class);

    $this->artisan('corex:audit:prune', ['--database' => AUDIT_TEST_CONNECTION])->assertSuccessful();

    expect(AuditLog::on(AUDIT_TEST_CONNECTION)->count())->toBe(1);
});

it('creates the BRIN index on occurred_at', function (): void {
    $index = auditPg()->selectOne(
        "select indexdef from pg_indexes where tablename = 'sys_audit_log' and indexname = 'sys_audit_log_occurred_brin_ix'",
    );

    expect($index)->not->toBeNull()
        ->and($index->indexdef)->toContain('USING brin');
});
