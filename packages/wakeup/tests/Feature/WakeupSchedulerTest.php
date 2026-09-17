<?php

declare(strict_types=1);

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\TenantContext;
use CoreX\Wakeup\Contracts\WakeupHandler;
use CoreX\Wakeup\Contracts\WakeupHint;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use CoreX\Wakeup\Contracts\WakeupSweeper;
use CoreX\Wakeup\Data\WakeupDelivery;
use CoreX\Wakeup\Data\WakeupRequest;
use CoreX\Wakeup\Data\WakeupWindow;
use CoreX\Wakeup\Tests\TestCase;
use CoreX\Wakeup\WakeupModule;
use Illuminate\Support\Facades\DB;

uses(TestCase::class);

final class WakeupTestHandler implements WakeupHandler
{
    public function handle(WakeupDelivery $delivery): void
    {
        DB::table('wakeup_effects')->insertOrIgnore(['request_key' => $delivery->request->requestKey]);
    }
}

final class FailingWakeupTestHandler implements WakeupHandler
{
    public function handle(WakeupDelivery $delivery): void
    {
        throw new RuntimeException('transient test failure');
    }
}

final class RecordingWakeupHint implements WakeupHint
{
    /** @var list<string> */
    public array $accounts = [];

    public function scheduled(TenantContext $context, DateTimeImmutable $fireAt): void
    {
        $this->accounts[] = $context->account->id;
    }
}

it('persists idempotent requests and completes the local handler once', function (): void {
    app()['config']->set('corex-wakeup.handlers', ['test.reminder' => WakeupTestHandler::class]);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    $scheduler = app(WakeupScheduler::class);
    $request = new WakeupRequest($context, 'test.reminder', 'reminder-1', new DateTimeImmutable('-1 minute'), null, ['message' => 'wake']);

    expect($scheduler->schedule($request)->status)->toBe('created')
        ->and($scheduler->schedule($request)->status)->toBe('existing');

    $result = app(WakeupSweeper::class)->sweep($context, 10);

    expect($result->completed)->toBe(1)
        ->and(DB::table('wakeup_effects')->where('request_key', 'reminder-1')->count())->toBe(1);
});

it('persists a retryable handler failure without reusing its generation', function (): void {
    app()['config']->set('corex-wakeup.handlers', ['test.failure' => FailingWakeupTestHandler::class]);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    app(WakeupScheduler::class)->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.failure',
        requestKey: 'failure-1',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: [],
    ));

    $result = app(WakeupSweeper::class)->sweep($context, 10);
    $row = DB::table('sys_wakeup_requests')->where('request_key', 'failure-1')->first();

    expect($result->retried)->toBe(1)
        ->and($row->status)->toBe('failed')
        ->and($row->generation)->toBe(1)
        ->and($row->attempt)->toBe(1)
        ->and($row->retry_at)->not->toBeNull();
});

it('rejects unsafe request data before writing a durable request', function (): void {
    app()['config']->set('corex-wakeup.handlers', ['test.reminder' => WakeupTestHandler::class]);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();

    expect(fn (): WakeupRequest => new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'not-utc',
        fireAt: new DateTimeImmutable('2026-09-15T12:00:00+03:00'),
        window: null,
        payload: [],
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(WakeupScheduler::class)->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'float-payload',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: ['amount' => 1.5],
    )))->toThrow(RuntimeException::class);

    expect(DB::table('sys_wakeup_requests')->count())->toBe(0);
});

it('keeps cancellation keys unambiguous across registered kinds and dispatches a best-effort hint', function (): void {
    app()['config']->set('corex-wakeup.handlers', [
        'test.reminder' => WakeupTestHandler::class,
        'test.secondary' => WakeupTestHandler::class,
    ]);
    $hint = new RecordingWakeupHint;
    app()->instance(RecordingWakeupHint::class, $hint);
    app()->tag(RecordingWakeupHint::class, 'corex.wakeup.hints');
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    $scheduler = app(WakeupScheduler::class);

    expect($scheduler->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'shared-key',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: [],
    ))->status)->toBe('created');

    expect($scheduler->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.secondary',
        requestKey: 'shared-key',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: [],
    ))->status)->toBe('conflict')
        ->and($hint->accounts)->toBe([$context->account->id])
        ->and(DB::table('sys_wakeup_requests')->count())->toBe(1);
});

it('makes cancellation outcomes explicit without depending on a root hint', function (): void {
    app()['config']->set('corex-wakeup.handlers', ['test.reminder' => WakeupTestHandler::class]);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    $scheduler = app(WakeupScheduler::class);

    expect($scheduler->cancel(context: $context, requestKey: 'missing'))->status->toBe('not-found');

    $scheduler->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'cancelled',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: ['nested' => ['b' => 2, 'a' => true]],
    ));

    expect($scheduler->cancel(context: $context, requestKey: 'cancelled'))->status->toBe('cancelled')
        ->and($scheduler->cancel(context: $context, requestKey: 'cancelled'))->status->toBe('already-cancelled');

    $scheduler->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'completed',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: [],
    ));
    DB::table('sys_wakeup_requests')->where('request_key', 'completed')->update(['status' => 'completed']);

    expect($scheduler->cancel(context: $context, requestKey: 'completed'))->status->toBe('already-completed');
});

it('rejects unregistered handlers and invalid window contracts before scheduling', function (): void {
    $context = app(TenantContextResolver::class)->current();
    $request = new WakeupRequest(
        context: $context,
        kind: 'test.unknown',
        requestKey: 'unknown-handler',
        fireAt: new DateTimeImmutable('2026-09-15T12:00:00Z'),
        window: null,
        payload: [],
    );

    expect(fn () => app(WakeupScheduler::class)->schedule($request))->toThrow(RuntimeException::class)
        ->and(fn (): WakeupWindow => new WakeupWindow(
            start: new DateTimeImmutable('2026-09-15T12:00:00+03:00'),
            end: new DateTimeImmutable('2026-09-15T13:00:00+03:00'),
            period: 'daily',
        ))->toThrow(InvalidArgumentException::class)
        ->and((new WakeupModule)->manifest()->composerName)->toBe('corex/wakeup');
});
