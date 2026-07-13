<?php

declare(strict_types=1);

use CoreX\Audit\QueuedAuditLogger;
use CoreX\Audit\RecordAuditEntry;
use CoreX\Contracts\AuditEntry;
use CoreX\Contracts\AuditLogger;
use CoreX\Enums\ActorType;
use CoreX\Models\AuditLog;
use CoreX\Tests\Stubs\StubAuditableThing;
use CoreX\Tests\Stubs\Widget;
use Flex\Modules\Audit\AuditCapabilityWiring;
use Flex\Modules\Enums\Capability;
use Flex\Modules\Registry\ArrayEntityRegistry;
use Flex\Modules\Registry\EntityDefinition;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses()->group('pg');

const AUDIT_TEST_CONNECTION = 'pgsql';

function auditPg(): Connection
{
    return DB::connection(AUDIT_TEST_CONNECTION);
}

function auditLogger(): QueuedAuditLogger
{
    return new QueuedAuditLogger(connection: AUDIT_TEST_CONNECTION);
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
    // CoreXTestCase disables audit by default (missing-table guard for the
    // sqlite lane, see its defineEnvironment) — this suite opts back in.
    config(['corex.audit.enabled' => true]);

    // D12: sys_audit_log is PG-only (jsonb/CHECK/BRIN). Reset to a clean
    // public schema so each test starts with no migration history (mirrors
    // SettingsTest's/ModuleLifecycleTest's pg-lane pattern).
    auditPg()->statement('DROP SCHEMA IF EXISTS public CASCADE');
    auditPg()->statement('CREATE SCHEMA public');
    DB::purge(AUDIT_TEST_CONNECTION);

    $this->artisan('migrate', [
        '--database' => AUDIT_TEST_CONNECTION,
        '--path' => realpath(__DIR__.'/../../packages/core/database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    $this->app->instance(AuditLogger::class, auditLogger());
});

it('enqueues on the sync path instead of writing synchronously (AC-23)', function (): void {
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

it('auto-observer records byCapability(Auditable) with sensitive masking (AC-20)', function (): void {
    Schema::create('stub_auditable_things', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('secret')->nullable();
        $table->timestamps();
    });

    $registry = new ArrayEntityRegistry;
    $registry->register(auditEntityFor(StubAuditableThing::class, 'stub_auditable_things', 'stub.auditable_thing'));

    AuditCapabilityWiring::wire($registry);

    $thing = StubAuditableThing::create(['name' => 'alpha', 'secret' => 'top-secret']);

    $entry = AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'created')->sole();

    expect($entry->entity_id)->toBe((string) $thing->id)
        ->and($entry->changes['name']['new'])->toBe('alpha')
        ->and($entry->changes['secret']['new'])->toBe('••••••');
});

it('auto-observer skips models that already opt in via HasAuditLog (no double record)', function (): void {
    $registry = new ArrayEntityRegistry;
    $registry->register(auditEntityFor(Widget::class, 'widgets', 'stub.widget'));

    AuditCapabilityWiring::wire($registry);

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

    AuditCapabilityWiring::wire($registry);

    StubAuditableThing::create(['name' => 'alpha']);

    $entry = AuditLog::on(AUDIT_TEST_CONNECTION)->where('action', 'created')->sole();

    expect($entry->actor_type)->toBe(ActorType::System)
        ->and($entry->actor_id)->toBeNull();
});

it('UUIDv7 primary key is a cursor — "after id X" pagination without OFFSET (AC-23)', function (): void {
    // Ordered by id, not occurred_at: occurred_at is timestamptz(0) — three
    // inserts microseconds apart round to the same second. The id itself
    // (UUIDv7) is the time-sortable cursor this AC is about.
    foreach (range(1, 3) as $i) {
        auditLogger()->record(new AuditEntry(
            action: 'created',
            actorType: ActorType::System,
            entityType: 'stub.thing',
            entityId: (string) Str::uuid7(),
        ));
    }

    [$first, $second, $third] = AuditLog::on(AUDIT_TEST_CONNECTION)->orderBy('id')->pluck('id')->all();

    $after = AuditLog::on(AUDIT_TEST_CONNECTION)
        ->where('id', '>', $first)
        ->orderBy('id')
        ->pluck('id')
        ->all();

    expect($after)->toBe([$second, $third]);
});

it('creates the BRIN index on occurred_at', function (): void {
    $index = auditPg()->selectOne(
        "select indexdef from pg_indexes where tablename = 'sys_audit_log' and indexname = 'sys_audit_brin_ix'",
    );

    expect($index)->not->toBeNull()
        ->and($index->indexdef)->toContain('USING brin');
});
