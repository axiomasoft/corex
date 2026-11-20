<?php

declare(strict_types=1);

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Scheduler\Internal\SchedulerOptions;
use CoreX\Scheduler\Internal\WakeupHandlerRegistry;
use CoreX\Scheduler\SchedulerServiceProvider;
use CoreX\Scheduler\Tests\TestCase;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Contracts\DueWakeupSweeper;
use CoreX\Scheduling\Contracts\WakeupScheduleHint;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupDelivery;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use CoreX\Scheduling\Data\WakeupIdentity;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use CoreX\Tests\Support\WorkScopeFixture;
use CoreX\Time\Laravel\SystemClock;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;

uses(TestCase::class);

final class WakeupTestHandler implements DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void
    {
        DB::table('wakeup_effects')->insert(['request_key' => $delivery->request->requestKey]);
    }
}

final class FailingWakeupTestHandler implements DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void
    {
        throw new RuntimeException('transient test failure');
    }
}

final class RecordingWakeupHint implements WakeupScheduleHint
{
    /** @var list<string> */
    public array $accounts = [];

    public function scheduled(TenantContext $context, DateTimeImmutable $fireAt): void
    {
        $this->accounts[] = $context->account->id;
    }
}

function wakeupRequest(
    string $key = 'reminder',
    ?WakeupWindow $window = null,
    ?JsonDocument $payload = null,
    string $kind = 'test.reminder',
    string $version = 'test/1',
    ?DateTimeImmutable $at = null,
): WakeupRequest {
    $context = resolve(TenantContextResolver::class)->current();

    return new WakeupRequest(resolve(DataScopeFactory::class)->forCurrentPrincipal($context), $kind, $version, $key,
        $at ?? new DateTimeImmutable('2026-06-01T12:00:00.123456Z'), $window, $payload ?? JsonDocuments::capture([]));
}

beforeEach(function (): void {
    config(['corex-scheduler.handlers' => [
        'test.reminder' => ['handler' => WakeupTestHandler::class, 'version' => 'test/1'],
        'test.failure' => ['handler' => FailingWakeupTestHandler::class, 'version' => 'test/1'],
    ]]);
    app()->forgetInstance(SchedulerOptions::class);
    app()->instance(ClockInterface::class, new FrozenClock(new DateTimeImmutable('2026-06-01T12:05:00Z')));
});

it('returns the same ref for reordered map keys and sends one hint after the outer commit', function (): void {
    $hint = new RecordingWakeupHint;
    app()->instance(RecordingWakeupHint::class, $hint);
    app()->tag(RecordingWakeupHint::class, WakeupScheduleHint::TAG);
    app()->forgetInstance(WakeupScheduler::class);
    $scheduler = resolve(WakeupScheduler::class);
    $start = new DateTimeImmutable('2026-06-01T10:00:00.123456Z');
    $end = $start->modify('+1 hour');
    $window = new WakeupWindow($start, $end, 'custom');
    $doc = new JsonDocument('{"b":[{},[]],"a":null}');
    $request = wakeupRequest(window: $window, payload: $doc);
    DB::transaction(function () use ($hint, $scheduler, $request, $window): void {
        $created = $scheduler->schedule($request);
        $replay = $scheduler->schedule(wakeupRequest(window: $window, payload: new JsonDocument('{"a":null,"b":[{},[]]}')));
        expect($created->status)->toBe('created')->and($replay->status)->toBe('existing')
            ->and($replay->id)->not->toBeNull()->and($replay->id)->toBe($created->id)
            ->and($hint->accounts)->toBe([]);
    });
    expect($hint->accounts)->toBe([$request->scope->accountId]);
    $this->artisan('corex:scheduler:sweep --limit=1')->expectsOutputToContain('completed 1')->assertSuccessful();
    expect(DB::table('wakeup_effects')->count())->toBe(1);
});

it('keeps exact replay valid across unrelated registry fingerprint changes', function (): void {
    $definition = new WakeupHandlerDefinition('test.reminder', 'test/1', WakeupTestHandler::class);
    $request = wakeupRequest();
    foreach (['registry-a' => 'created', 'registry-b' => 'existing'] as $fingerprint => $result) {
        $registry = WakeupHandlerRegistry::compile([$definition], $fingerprint);
        app()->instance(WakeupHandlerRegistry::class, $registry);
        app()->forgetInstance(WakeupScheduler::class);
        expect(resolve(WakeupScheduler::class)->schedule($request)->status)->toBe($result);
    }
    $this->artisan('corex:scheduler:sweep')->expectsOutputToContain('completed 1')->assertSuccessful();
});

it('quarantines incompatible stamps and scope without dispatch or retry', function (): void {
    $request = wakeupRequest();
    $scheduler = resolve(WakeupScheduler::class);
    foreach (['missing', 'mismatch', 'scope'] as $key) {
        $scheduler->schedule(wakeupRequest($key));
    }
    $rows = DB::table('sys_wakeup_requests');
    (clone $rows)->where('request_key', 'missing')->update(['payload' => '{}']);
    $envelope = json_decode((clone $rows)->where('request_key', 'mismatch')->value('payload'), true, flags: JSON_THROW_ON_ERROR);
    expect($envelope['_corex']['extension']['version'])->toBe($request->handlerVersion);
    $envelope['_corex']['extension']['version'] = 'retired/1';
    (clone $rows)->where('request_key', 'mismatch')->update(['payload' => json_encode($envelope, JSON_THROW_ON_ERROR)]);
    (clone $rows)->where('request_key', 'scope')->update(['data_scope' => '{"version":1}']);
    $context = resolve(TenantContextResolver::class)->current();
    $sweeper = resolve(DueWakeupSweeper::class);
    expect($sweeper->sweep($context, 10)->failed)->toBe(3)
        ->and($rows->pluck('failure_code', 'request_key')->all())->toMatchArray([
            'missing' => 'handler-stamp-missing', 'mismatch' => 'handler-version-mismatch', 'scope' => 'scope-unavailable',
        ])->and((clone $rows)->where('status', 'quarantined')->count())->toBe(3)
        ->and($sweeper->nextDue($context))->toBeNull()
        ->and($sweeper->sweep($context, 10)->claimed)->toBe(0)
        ->and(DB::table('wakeup_effects')->count())->toBe(0);
});

it('cancels the exact kind and records cancellation between claim and dispatch', function (): void {
    $scheduler = resolve(WakeupScheduler::class);
    $request = wakeupRequest();
    $identity = new WakeupIdentity($request->kind, $request->requestKey);
    expect($scheduler->cancel($request->scope, $identity)->status)->toBe('not-found');
    $scheduler->schedule($request);
    $other = wakeupRequest(kind: 'test.failure');
    expect($scheduler->schedule($other)->status)->toBe('created');
    $otherId = new WakeupIdentity($other->kind, $other->requestKey);
    expect($scheduler->cancel($other->scope, $otherId)->status)->toBe('cancelled')
        ->and($scheduler->cancel($other->scope, $otherId)->status)->toBe('already-cancelled');
    $cancelled = false;
    Event::listen(TransactionCommitted::class, function ($event) use ($request, $identity, &$cancelled): void {
        if ($cancelled || $event->connection !== DB::connection() || $event->connection->transactionLevel() !== 0) {
            return;
        }

        if (DB::table('sys_wakeup_requests')->where('status', 'running')->exists()) {
            $cancelled = true;
            expect(resolve(WakeupScheduler::class)->cancel($request->scope, $identity)->status)->toBe('cancel-requested');
        }
    });
    $result = resolve(DueWakeupSweeper::class)->sweep(resolve(TenantContextResolver::class)->current(), 1);
    expect($cancelled)->toBeTrue()->and($result->skipped)->toBe(1)
        ->and(DB::table('sys_wakeup_requests')->where('status', 'cancelled')->count())->toBe(2)
        ->and(DB::table('wakeup_effects')->count())->toBe(0);
});

it('uses the same due time and attempt limit for retries and nextDue', function (): void {
    config(['corex-scheduler.max_attempts' => 3]);
    app()->forgetInstance(SchedulerOptions::class);
    $clock = resolve(ClockInterface::class);
    $context = resolve(TenantContextResolver::class)->current();
    resolve(WakeupScheduler::class)->schedule(wakeupRequest(kind: 'test.failure'));
    $sweeper = resolve(DueWakeupSweeper::class);
    foreach ([2, 4] as $seconds) {
        $retry = $clock->now()->modify("+{$seconds} seconds");
        expect($sweeper->sweep($context, 1)->retried)->toBe(1)
            ->and($sweeper->nextDue($context))->toEqual($retry)
            ->and($sweeper->sweep($context, 1)->claimed)->toBe(0);
        $clock->advance(new DateInterval("PT{$seconds}S"));
    }
    expect($sweeper->sweep($context, 1)->failed)->toBe(1)
        ->and($sweeper->nextDue($context))->toBeNull()
        ->and(DB::table('sys_wakeup_requests')->value('status'))->toBe('exhausted');
});

it('validates UTC instants, period lengths and the fire time before a request can be stored', function (): void {
    $end = new DateTimeImmutable('2026-06-01T12:00:00Z');
    $day = new WakeupWindow($end->modify('-24 hours'), $end, 'daily');
    expect(fn () => wakeupRequest(at: new DateTimeImmutable('2026-06-01T12:00:00+03:00')))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new WakeupWindow($end->modify('-3 hours'), $end, 'hourly'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => wakeupRequest(window: $day, at: $end->modify('-1 microsecond')))->toThrow(InvalidArgumentException::class);
    expect(resolve(WakeupScheduler::class)->schedule(wakeupRequest(window: $day, at: $end))->status)->toBe('created');
});

it('conflicts when a replay changes any window component or the UTC instant', function (): void {
    $start = new DateTimeImmutable('2026-06-01T10:00:00Z');
    $end = $start->modify('+1 hour');
    $window = new WakeupWindow($start, $end, 'custom');
    $original = wakeupRequest(window: $window);
    $scheduler = resolve(WakeupScheduler::class);
    $created = $scheduler->schedule($original);
    foreach ([
        wakeupRequest(window: null),
        wakeupRequest(window: new WakeupWindow($start->modify('-1 microsecond'), $end, 'custom')),
        wakeupRequest(window: new WakeupWindow($start, $end->modify('+1 microsecond'), 'custom')),
        wakeupRequest(window: new WakeupWindow($start, $end, 'hourly')),
        wakeupRequest(window: $window, at: $original->fireAt->modify('+1 microsecond')),
    ] as $changed) {
        expect($scheduler->schedule($changed)->status)->toBe('conflict');
    }
    expect($scheduler->schedule($original)->id)->toBe($created->id)
        ->and(DB::table('sys_wakeup_requests')->count())->toBe(1);
});

it('preserves list order, scalar types and omitted fields in payload replay identity', function (): void {
    $scheduler = resolve(WakeupScheduler::class);
    $original = wakeupRequest(payload: new JsonDocument('{"a":null,"b":[{},[]],"n":1}'));
    $scheduler->schedule($original);
    foreach (['{"b":[{},[]],"n":1}', '{"a":null,"b":[[],{}],"n":1}', '{"a":null,"b":[{},[]],"n":"1"}'] as $json) {
        expect($scheduler->schedule(wakeupRequest(payload: new JsonDocument($json)))->status)->toBe('conflict');
    }
    expect($scheduler->schedule($original)->status)->toBe('existing');
});

it('rejects unsafe or oversized payloads before writing a request', function (): void {
    $scheduler = resolve(WakeupScheduler::class);
    // With the eight bytes of object syntax this string reaches the exact document limit.
    $max = str_repeat('x', 65_528);
    foreach ([['v' => 1.5], ['v' => range(0, 256)], ['v' => $max.'x']] as $value) {
        expect(fn () => $scheduler->schedule(wakeupRequest(payload: JsonDocuments::capture($value))))->toThrow(RuntimeException::class);
    }
    expect(DB::table('sys_wakeup_requests')->count())->toBe(0)
        ->and($scheduler->schedule(wakeupRequest(payload: JsonDocuments::capture(['v' => $max])))->status)->toBe('created');
});

it('requires the declared handler kind and version and keeps an existing receipt on version conflict', function (): void {
    $scheduler = resolve(WakeupScheduler::class);
    expect(fn () => $scheduler->schedule(wakeupRequest(kind: 'absent')))->toThrow(RuntimeException::class)
        ->and(fn () => $scheduler->schedule(wakeupRequest(version: 'test/2')))->toThrow(RuntimeException::class)
        ->and(DB::table('sys_wakeup_requests')->count())->toBe(0);
    $created = $scheduler->schedule(wakeupRequest());
    expect($scheduler->schedule(wakeupRequest(version: 'test/2'))->status)->toBe('conflict')
        ->and(DB::table('sys_wakeup_requests')->value('id'))->toBe($created->id);
});

it('delivers only the producer payload, original scope and stable effect key to the handler', function (): void {
    $probe = new class implements DueWakeupHandler
    {
        public ?WakeupDelivery $delivery = null;

        public function handle(WakeupDelivery $delivery): void
        {
            $this->delivery = $delivery;
        }
    };
    app()->bind(WakeupTestHandler::class, fn () => $probe);
    $request = wakeupRequest(payload: new JsonDocument('{"_corex":{"customer":"value"},"item":7}'));
    $ref = resolve(WakeupScheduler::class)->schedule($request)->id;
    $row = DB::table('sys_wakeup_requests')->where('id', $ref)->firstOrFail();
    $envelope = json_decode($row->payload, true, flags: JSON_THROW_ON_ERROR);
    expect($envelope['_corex']['extension']['point'])->toBe('wakeup.handler')
        ->and($envelope['_corex']['extension']['version'])->toBe('test/1');
    $this->artisan('corex:scheduler:sweep')->expectsOutputToContain('completed 1')->assertSuccessful();
    expect(JsonDocuments::map($probe->delivery->request->payload))->toBe(JsonDocuments::map($request->payload))
        ->and($probe->delivery->request->scope)->toEqual($request->scope)
        ->and($probe->delivery->context->account->id)->toBe($request->scope->accountId)
        ->and($probe->delivery->idempotencyKey)->toBe('wakeup:'.$ref);
});

it('discards the pending request and hint when the outer transaction rolls back', function (): void {
    $hint = new RecordingWakeupHint;
    app()->instance(RecordingWakeupHint::class, $hint);
    app()->tag(RecordingWakeupHint::class, WakeupScheduleHint::TAG);
    $request = wakeupRequest();
    $scheduler = resolve(WakeupScheduler::class);
    DB::beginTransaction();

    try {
        expect($scheduler->schedule($request)->status)->toBe('created')
            ->and(DB::table('sys_wakeup_requests')->count())->toBe(1);
    } finally {
        DB::rollBack();
    }
    expect($hint->accounts)->toBe([])->and(DB::table('sys_wakeup_requests')->count())->toBe(0)
        ->and($scheduler->schedule($request)->status)->toBe('created')
        ->and($hint->accounts)->toBe([$request->scope->accountId]);
});

it('keeps the original actor’s request intact when another actor uses the same identity', function (): void {
    $scheduler = resolve(WakeupScheduler::class);
    $original = wakeupRequest();
    $scheduler->schedule($original);
    $identity = new WakeupIdentity($original->kind, $original->requestKey);
    resolve(WorkScopeFixture::class)->principal = new ScopePrincipal('user', 'actor-2');
    expect(fn () => $scheduler->cancel($original->scope, $identity))->toThrow(ScopeContextMismatch::class)
        ->and(fn () => $scheduler->schedule($original))->toThrow(ScopeContextMismatch::class)
        ->and($scheduler->cancel(wakeupRequest()->scope, $identity)->status)->toBe('not-found')
        ->and($scheduler->schedule(wakeupRequest())->status)->toBe('conflict')
        ->and(DB::table('sys_wakeup_requests')->value('status'))->toBe('pending');
    resolve(WorkScopeFixture::class)->principal = new ScopePrincipal('user', 'actor-1');
    expect($scheduler->cancel($original->scope, $identity)->status)->toBe('cancelled');
});

it('preserves the completed receipt on cancellation and on replay after its handler retires', function (): void {
    $scheduler = resolve(WakeupScheduler::class);
    $original = wakeupRequest();
    $created = $scheduler->schedule($original);
    $this->artisan('corex:scheduler:sweep')->expectsOutputToContain('completed 1')->assertSuccessful();
    expect($scheduler->cancel($original->scope, new WakeupIdentity($original->kind, $original->requestKey))->status)->toBe('already-completed');
    app()->instance(WakeupHandlerRegistry::class, WakeupHandlerRegistry::compile([], 'retired'));
    app()->forgetInstance(WakeupScheduler::class);
    $replay = resolve(WakeupScheduler::class)->schedule($original);
    expect($replay->status)->toBe('existing')->and($replay->id)->toBe($created->id);
    $this->artisan('corex:scheduler:sweep')->expectsOutputToContain('Claimed 0')->assertSuccessful();
    expect(DB::table('wakeup_effects')->count())->toBe(1);
});

it('leaves a future wakeup pending until the exact fire instant', function (): void {
    $clock = resolve(ClockInterface::class);
    $at = $clock->now()->modify('+1 microsecond');
    resolve(WakeupScheduler::class)->schedule(wakeupRequest(at: $at));
    $sweeper = resolve(DueWakeupSweeper::class);
    $context = resolve(TenantContextResolver::class)->current();
    expect($sweeper->nextDue($context))->toEqual($at)
        ->and($sweeper->sweep($context, 1)->claimed)->toBe(0)
        ->and(DB::table('sys_wakeup_requests')->value('attempt'))->toBe(0);
    $clock->advance(DateInterval::createFromDateString('1 microsecond'));
    expect($sweeper->sweep($context, 1)->completed)->toBe(1)->and($sweeper->nextDue($context))->toBeNull();
});

it('exhausts a final running attempt exactly when its lease ends', function (): void {
    config(['corex-scheduler.max_attempts' => 1]);
    app()->forgetInstance(SchedulerOptions::class);
    $clock = resolve(ClockInterface::class);
    $deadline = $clock->now()->modify('+1 second');
    $ref = resolve(WakeupScheduler::class)->schedule(wakeupRequest())->id;
    $row = DB::table('sys_wakeup_requests')->where('id', $ref);
    $row->update(['status' => 'running', 'attempt' => 1, 'generation' => 1, 'claim_token' => (string) Str::uuid7(),
        'lease_until' => $deadline->format('Y-m-d H:i:s.uP')]);
    $sweeper = resolve(DueWakeupSweeper::class);
    $context = resolve(TenantContextResolver::class)->current();
    expect($sweeper->nextDue($context))->toEqual($deadline)
        ->and($sweeper->sweep($context, 1)->claimed)->toBe(0)
        ->and($row->value('status'))->toBe('running');
    $clock->advance(new DateInterval('PT1S'));
    $sweeper->sweep($context, 1);
    expect($row->value('status'))->toBe('exhausted')->and($row->value('failure_code'))->toBe('lease-expired')
        ->and($sweeper->nextDue($context))->toBeNull()->and(DB::table('wakeup_effects')->count())->toBe(0);
});

it('rolls back a failed handler write and retries with a new generation and the same idempotency key', function (): void {
    $handler = new class implements DueWakeupHandler
    {
        /** @var list<array{int, int, string}> */
        public array $attempts = [];

        public function handle(WakeupDelivery $delivery): void
        {
            $this->attempts[] = [$delivery->generation, $delivery->attempt, $delivery->idempotencyKey];
            DB::table('wakeup_effects')->insert(['request_key' => $delivery->request->requestKey]);

            if ($delivery->attempt === 1) {
                throw new RuntimeException('Failure after the handler write');
            }
        }
    };
    app()->bind(WakeupTestHandler::class, fn () => $handler);
    $ref = resolve(WakeupScheduler::class)->schedule(wakeupRequest())->id;
    $sweeper = resolve(DueWakeupSweeper::class);
    $context = resolve(TenantContextResolver::class)->current();
    expect($sweeper->sweep($context, 1)->retried)->toBe(1)->and(DB::table('wakeup_effects')->count())->toBe(0)
        ->and(DB::table('sys_wakeup_requests')->value('claim_token'))->toBeNull();
    resolve(ClockInterface::class)->advance(new DateInterval('PT2S'));
    expect($sweeper->sweep($context, 1)->completed)->toBe(1)
        ->and($handler->attempts)->toBe([[1, 1, 'wakeup:'.$ref], [2, 2, 'wakeup:'.$ref]])
        ->and(DB::table('wakeup_effects')->count())->toBe(1);
});

it('rejects a genuine work proof issued for another owner before admitting a wakeup', function (): void {
    $request = wakeupRequest();
    $id = resolve(WakeupScheduler::class)->schedule($request)->id;
    $row = DB::table('sys_wakeup_requests')->where('id', $id);
    $reference = hash('sha256', json_encode([$id, (clone $row)->value('request_digest')], JSON_THROW_ON_ERROR));
    $proof = resolve(StoredWorkIssuer::class)->issue($request->scope, 'corex.queue', $reference);
    $row->update(['scope_seal' => $proof->seal]);
    $sweeper = resolve(DueWakeupSweeper::class);
    $result = $sweeper->sweep(resolve(TenantContextResolver::class)->current(), 1);
    expect($result->failed)->toBe(1);
    expect($row->value('status'))->toBe('quarantined')
        ->and(DB::table('wakeup_effects')->count())->toBe(0)
        ->and(DB::connection('work-scope-control')->table('corex_scope_tickets')->where('operation_id', 'wakeup:'.$id)->count())->toBe(0);
});

it('installs one UTC system clock when the host has no clock binding', function (): void {
    unset(app()[ClockInterface::class]);
    (new SchedulerServiceProvider(app()))->register();
    $clock = resolve(ClockInterface::class);
    expect($clock)->toBeInstanceOf(SystemClock::class)->and($clock->now()->getTimezone()->getName())->toBe('UTC')
        ->and(resolve(ClockInterface::class))->toBe($clock);
    resolve(WakeupScheduler::class)->schedule(wakeupRequest(at: $clock->now()->modify('-1 minute')));
    $this->artisan('corex:scheduler:sweep')->expectsOutputToContain('completed 1')->assertSuccessful();
});

it('uses the host clock when the provider registers over an existing binding', function (): void {
    $clock = resolve(ClockInterface::class);
    (new SchedulerServiceProvider(app()))->register();
    expect(resolve(ClockInterface::class))->toBe($clock);
    $due = $clock->now()->modify('+1 minute');
    resolve(WakeupScheduler::class)->schedule(wakeupRequest(at: $due));
    $this->artisan('corex:scheduler:sweep')->expectsOutputToContain('Claimed 0')->assertSuccessful();
    $clock->advance(new DateInterval('PT1M'));
    $this->artisan('corex:scheduler:sweep')->expectsOutputToContain('completed 1')->assertSuccessful();
});
