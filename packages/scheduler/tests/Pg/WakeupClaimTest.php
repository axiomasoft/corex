<?php

declare(strict_types=1);

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Scheduler\Internal\SchedulerOptions;
use CoreX\Scheduler\Tests\PgTestCase;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Contracts\DueWakeupSweeper;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupDelivery;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

uses(PgTestCase::class)->group('pg');

final class PgVersionedWakeupHandler implements DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void
    {
        DB::table('wakeup_effects')->insert(['request_key' => $delivery->request->requestKey]);

        if ($delivery->request->requestKey === 'restore-in-flight') {
            $control = resolve(DatabaseScopeControl::class);
            $control->close($delivery->request->scope->accountId, 1);
            // A live admission ticket must prevent restore before this transaction exits.
            expect(fn () => $control->drain($delivery->request->scope->accountId, 2))->toThrow(ScopeContextMismatch::class);
        }

        if ($delivery->request->requestKey === 'lease-expiry' && $delivery->attempt === 1) {
            resolve(ClockInterface::class)->advance(new DateInterval('PT60S'));
        }
    }
}

beforeEach(function (): void {
    DB::statement("set time zone 'Asia/Kathmandu'");
    config(['corex-scheduler.handlers' => [
        'test.pg-versioned' => ['handler' => PgVersionedWakeupHandler::class, 'version' => 'pg-versioned/1'],
    ]]);
    app()->forgetInstance(SchedulerOptions::class);
    app()->instance(ClockInterface::class, new FrozenClock(new DateTimeImmutable('2026-10-01T12:00:00.123456Z')));
});

function pgWakeup(string $key): WakeupRequest
{
    $context = resolve(TenantContextResolver::class)->current();

    return new WakeupRequest(resolve(DataScopeFactory::class)->forCurrentPrincipal($context), 'test.pg-versioned',
        'pg-versioned/1', $key, new DateTimeImmutable('2026-10-01T11:59:59.123456Z'), null, JsonDocuments::capture(['message' => 'wake']));
}

it('skips a locked due row and claims the next one on the real sweep entrypoint', function (): void {
    $scheduler = resolve(WakeupScheduler::class);
    $first = $scheduler->schedule(pgWakeup('first'));
    $scheduler->schedule(pgWakeup('second'));
    config(['database.connections.holder' => config('database.connections.pgsql')]);
    $holder = DB::connection('holder');
    expect($holder->scalar('select pg_backend_pid()'))->not->toBe(DB::scalar('select pg_backend_pid()'));
    $holder->beginTransaction();

    try {
        $holder->table('sys_wakeup_requests')->where('id', $first->id)->lockForUpdate()->firstOrFail();
        DB::statement("set lock_timeout = '500ms'");
        $this->artisan('corex:scheduler:sweep --limit=1')->expectsOutputToContain('completed 1')->assertSuccessful();
        expect(DB::table('wakeup_effects')->pluck('request_key')->all())->toBe(['second']);
    } finally {
        $holder->rollBack();
    }
    $this->artisan('corex:scheduler:sweep --limit=1')->expectsOutputToContain('completed 1')->assertSuccessful();
    expect(DB::table('sys_wakeup_requests')->where('status', 'completed')->count())->toBe(2);
});

it('replays a concurrent insert inside an outer transaction and preserves further writes', function (): void {
    $request = pgWakeup('race');
    $scheduler = resolve(WakeupScheduler::class);
    $receipt = $scheduler->schedule($request);
    $rows = DB::table('sys_wakeup_requests');
    $row = (array) $rows->where('id', $receipt->id)->firstOrFail();
    $rows->delete();
    $process = proc_open([PHP_BINARY, __DIR__.'/../Support/competing-wakeup-insert.php'],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

    try {
        $self = DB::scalar('select pg_backend_pid()');
        fwrite($pipes[0], json_encode([config('database.connections.pgsql'), $row, $self], JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $other = (int) fgets($pipes[1]);
        expect($other)->toBeGreaterThan(0)->not->toBe($self);
        DB::transaction(function () use ($scheduler, $request, $receipt): void {
            $existing = $scheduler->schedule($request);
            expect($existing->status)->toBe('existing')->and($existing->id)->toBe($receipt->id);
            DB::table('wakeup_effects')->insert(['request_key' => 'outer-transaction']);
        });
        expect(DB::table('wakeup_effects')->count())->toBe(1)->and($rows->count())->toBe(1);
    } finally {
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        expect(proc_close($process))->toBe(0, $stderr);
    }
});

it('rolls back the local effect at lease expiry and admits it once after reclaim', function (): void {
    $request = pgWakeup('lease-expiry');
    resolve(WakeupScheduler::class)->schedule($request);
    $sweeper = resolve(DueWakeupSweeper::class);
    $context = resolve(TenantContextResolver::class)->current();
    $first = $sweeper->sweep($context, 1);
    $row = DB::table('sys_wakeup_requests')->firstOrFail();
    $envelope = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
    $ticket = DB::connection('work-scope-control')->table('corex_scope_tickets')->where('operation_id', 'wakeup:'.$row->id)->where('attempt', 1)->firstOrFail();
    expect($ticket->state)->toBe('rolled-back')->and($ticket->effect_key)->toBe('wakeup:'.$row->id);
    expect($first->completed)->toBe(0)->and($first->skipped)->toBe(1)
        ->and($row->status)->toBe('running')->and($row->claim_token)->not->toBeNull()
        ->and($envelope['_corex']['extension']['version'])->toBe($request->handlerVersion)
        ->and($envelope['data'])->toBe(['message' => 'wake'])
        ->and(DB::table('wakeup_effects')->count())->toBe(0)
        ->and($sweeper->sweep($context, 1)->completed)->toBe(1)
        ->and(DB::table('sys_wakeup_requests')->value('generation'))->toBe(2)
        ->and(DB::table('wakeup_effects')->count())->toBe(1);
});

it('drains an admitted handler and quarantines its restored snapshot before the next runnable row', function (): void {
    $scheduler = resolve(WakeupScheduler::class);
    $request = pgWakeup('restore-in-flight');
    $admitted = $scheduler->schedule($request);
    $rows = DB::table('sys_wakeup_requests');
    $snapshot = (array) (clone $rows)->where('id', $admitted->id)->firstOrFail();
    $sweeper = resolve(DueWakeupSweeper::class);
    $context = resolve(TenantContextResolver::class)->current();
    $control = resolve(DatabaseScopeControl::class);
    expect(fn () => $sweeper->sweep($context, 1))->toThrow(ScopeContextMismatch::class)
        ->and(DB::table('wakeup_effects')->pluck('request_key')->all())->toBe(['restore-in-flight'])
        ->and($rows->value('status'))->toBe('completed');
    $closed = $control->drain($context->account->id, 2);
    // Restore the pre-execution tenant snapshot while admission is closed. The
    // independent control database still holds the original commit evidence.
    DB::transaction(function () use ($rows, $snapshot): void {
        $rows->delete();
        $rows->insert($snapshot);
        DB::table('wakeup_effects')->delete();
    });
    expect(DB::connection('work-scope-control')->table('corex_scope_tickets')
        ->where('operation_id', 'wakeup:'.$admitted->id)->where('attempt', 1)->value('state'))->toBe('committed');
    $old = $control->resolve($context->account);
    $new = new PhysicalBinding($old->deploymentId, $old->bindingId, $old->bindingGeneration, $old->accountIncarnation, hash('sha256', 'restored'));
    $control->reopen($context->account->id, $closed, $new, static fn (PhysicalBinding $binding): bool => $binding === $new);
    resolve(ClockInterface::class)->advance(new DateInterval('PT60S'));
    $scheduler->schedule(pgWakeup('fresh'));
    $result = $sweeper->sweep($context, 2);
    expect($result->completed)->toBe(1)->and($result->failed)->toBe(1)
        ->and(DB::table('sys_wakeup_requests')->where('request_key', 'restore-in-flight')->value('status'))->toBe('quarantined')
        ->and(DB::table('wakeup_effects')->pluck('request_key')->all())->toBe(['fresh']);
});

it('quarantines modified principals and transplanted seals before admission while valid signed work runs', function (): void {
    $scheduler = resolve(WakeupScheduler::class);
    $refs = [];
    foreach (['valid', 'actor-changed', 'other-seal', 'digest-changed'] as $key) {
        $refs[$key] = $scheduler->schedule(pgWakeup($key))->id;
    }
    $rows = DB::table('sys_wakeup_requests');
    $valid = (clone $rows)->where('id', $refs['valid'])->firstOrFail();
    $scope = json_decode($valid->data_scope, true, flags: JSON_THROW_ON_ERROR);
    $scope['principal_id'] = 'other-actor';
    (clone $rows)->where('id', $refs['actor-changed'])->update(['data_scope' => json_encode($scope, JSON_THROW_ON_ERROR)]);
    (clone $rows)->where('id', $refs['other-seal'])->update(['scope_seal' => $valid->scope_seal]);
    (clone $rows)->where('id', $refs['digest-changed'])->update(['request_digest' => $valid->request_digest]);

    $this->artisan('corex:scheduler:sweep --limit=4')->expectsOutputToContain('Claimed 4; completed 1')->assertSuccessful();
    expect(DB::table('wakeup_effects')->pluck('request_key')->all())->toBe(['valid'])
        ->and((clone $rows)->where('status', 'quarantined')->where('failure_code', 'scope-unavailable')->count())->toBe(3)
        ->and(DB::connection('work-scope-control')->table('corex_scope_tickets')
            ->where('operation_id', 'like', 'wakeup:%')->pluck('operation_id')->all())->toBe(['wakeup:'.$refs['valid']]);
});

it('serves the claim and next-due queries from the due index without sorting future wakeups', function (): void {
    $scheduler = resolve(WakeupScheduler::class);
    $due = $scheduler->schedule(pgWakeup('due-now'));
    // Thousands of not-yet-due rows of the same account: without the expression index both queries
    // read every one of them and sort by due time before they can return the first due row.
    DB::statement(<<<'SQL'
        insert into sys_wakeup_requests (id, account_id, account_incarnation, data_scope, scope_seal, workspace_scope_key, kind,
            request_key, request_digest, fire_at, payload, status, generation, attempt, created_at, updated_at)
        select gen_random_uuid(), account_id, account_incarnation, data_scope, scope_seal, workspace_scope_key, kind,
            'future-'||g, request_digest, fire_at + make_interval(mins => g), payload, 'pending', 0, 0, created_at, updated_at
        from sys_wakeup_requests, generate_series(1, 5000) g where id = ?
        SQL, [$due->id]);
    DB::statement('analyze sys_wakeup_requests');
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'sys_wakeup_requests') && str_contains($query->sql, 'case when status')) {
            $queries[] = [$query->sql, $query->bindings];
        }
    });

    $this->artisan('corex:scheduler:sweep --limit=1')->expectsOutputToContain('completed 1')->assertSuccessful();

    expect($queries)->toHaveCount(2);
    foreach ($queries as [$sql, $bindings]) {
        $plan = (string) DB::scalar('explain (format json) '.$sql, $bindings);
        expect($plan)->toContain('"Index Name": "sys_wakeup_requests_runnable_due_ix"')
            ->and($plan)->not->toContain('"Node Type": "Sort"');
    }
});
