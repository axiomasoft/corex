<?php

declare(strict_types=1);

use CoreX\Config\Config;
use CoreX\Time\FrozenClock;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Psr\Clock\ClockInterface;

function pruneOutboxInstallSchema(): void
{
    Schema::create(Config::outboxMessagesTable(), function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('name');
        $table->unsignedSmallInteger('contract_version');
        $table->unsignedSmallInteger('envelope_version');
        $table->string('source');
        $table->string('subject')->nullable();
        $table->string('correlation_id')->nullable();
        $table->string('causation_id')->nullable();
        $table->string('pii_class');
        $table->text('scope');
        $table->text('payload');
        $table->text('metadata');
        $table->dateTimeTz('occurred_at');
        $table->dateTimeTz('available_at');
        $table->string('status');
        $table->string('lease_owner')->nullable();
        $table->dateTimeTz('lease_until')->nullable();
        $table->unsignedInteger('attempts');
        $table->string('failure_class')->nullable();
        $table->text('last_error')->nullable();
        $table->dateTimeTz('published_at')->nullable();
        $table->dateTimeTz('dead_at')->nullable();
        $table->dateTimeTz('created_at');
        $table->dateTimeTz('updated_at');
    });

    Schema::create(Config::inboxMessagesTable(), function (Blueprint $table): void {
        $table->string('consumer');
        $table->string('message_id');
        $table->string('name');
        $table->dateTimeTz('processed_at');
        $table->primary(['consumer', 'message_id']);
    });
}

function pruneOutboxInsertRow(array $overrides = []): void
{
    $now = app(ClockInterface::class)->now();

    DB::table(Config::outboxMessagesTable())->insert([
        'id' => $overrides['id'] ?? 'msg-prune-1',
        'name' => 'membership.expired',
        'contract_version' => 1,
        'envelope_version' => 1,
        'source' => 'axiomasoft/corex',
        'subject' => null,
        'correlation_id' => null,
        'causation_id' => null,
        'pii_class' => 'none',
        'scope' => '{}',
        'payload' => '{}',
        'metadata' => '{}',
        'occurred_at' => $overrides['occurred_at'] ?? $now,
        'available_at' => $overrides['available_at'] ?? $now,
        'status' => $overrides['status'] ?? 'pending',
        'lease_owner' => null,
        'lease_until' => null,
        'attempts' => $overrides['attempts'] ?? 0,
        'failure_class' => null,
        'last_error' => null,
        'published_at' => $overrides['published_at'] ?? null,
        'dead_at' => $overrides['dead_at'] ?? null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

beforeEach(function (): void {
    pruneOutboxInstallSchema();
    app()->instance(ClockInterface::class, new FrozenClock(new DateTimeImmutable('2026-06-15T12:00:00+00:00')));
});

it('prunes published rows older than the published window and keeps newer ones', function (): void {
    $now = app(ClockInterface::class)->now();
    pruneOutboxInsertRow([
        'id' => 'pub-old',
        'status' => 'published',
        'published_at' => $now->sub(new DateInterval('P20D')),
    ]);
    pruneOutboxInsertRow([
        'id' => 'pub-new',
        'status' => 'published',
        'published_at' => $now->sub(new DateInterval('P5D')),
    ]);

    Artisan::call('corex:messaging:prune', ['--published-older-than' => '14 days']);

    expect(DB::table(Config::outboxMessagesTable())->pluck('id')->all())
        ->toBe(['pub-new']);
});

it('prunes dead rows by the dead window not the published window', function (): void {
    $now = app(ClockInterface::class)->now();
    pruneOutboxInsertRow([
        'id' => 'dead-old',
        'status' => 'dead',
        'dead_at' => $now->sub(new DateInterval('P100D')),
    ]);
    pruneOutboxInsertRow([
        'id' => 'dead-recent',
        'status' => 'dead',
        'dead_at' => $now->sub(new DateInterval('P30D')),
    ]);

    Artisan::call('corex:messaging:prune', [
        '--published-older-than' => '1 day',
        '--dead-older-than' => '90 days',
    ]);

    expect(DB::table(Config::outboxMessagesTable())->pluck('id')->all())
        ->toBe(['dead-recent']);
});

it('never prunes pending or publishing rows', function (): void {
    $now = app(ClockInterface::class)->now();
    pruneOutboxInsertRow([
        'id' => 'pending-1',
        'status' => 'pending',
        'occurred_at' => $now->sub(new DateInterval('P365D')),
    ]);
    pruneOutboxInsertRow([
        'id' => 'publishing-1',
        'status' => 'publishing',
        'occurred_at' => $now->sub(new DateInterval('P365D')),
    ]);

    Artisan::call('corex:messaging:prune', [
        '--published-older-than' => '0 seconds',
        '--dead-older-than' => '0 seconds',
    ]);

    expect(DB::table(Config::outboxMessagesTable())->count())->toBe(2);
});

it('does not touch sys_inbox_messages', function (): void {
    $now = app(ClockInterface::class)->now();
    pruneOutboxInsertRow([
        'id' => 'pub-stale',
        'status' => 'published',
        'published_at' => $now->sub(new DateInterval('P30D')),
    ]);

    DB::table(Config::inboxMessagesTable())->insert([
        'consumer' => 'worker-a',
        'message_id' => 'inbox-1',
        'name' => 'membership.expired',
        'processed_at' => $now,
    ]);

    Artisan::call('corex:messaging:prune', ['--published-older-than' => '1 day']);

    expect(DB::table(Config::inboxMessagesTable())->count())->toBe(1);
});

it('uses FrozenClock for retention thresholds', function (): void {
    $anchor = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    app()->instance(ClockInterface::class, new FrozenClock($anchor));

    pruneOutboxInsertRow([
        'id' => 'on-boundary',
        'status' => 'published',
        'published_at' => $anchor->sub(new DateInterval('P14D')),
    ]);
    pruneOutboxInsertRow([
        'id' => 'past-boundary',
        'status' => 'published',
        'published_at' => $anchor->sub(new DateInterval('P14DT1S')),
    ]);

    Artisan::call('corex:messaging:prune', ['--published-older-than' => '14 days']);

    expect(DB::table(Config::outboxMessagesTable())->pluck('id')->all())
        ->toBe(['on-boundary']);
});
