<?php

declare(strict_types=1);

use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Wakeup\Contracts\WakeupHandler;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use CoreX\Wakeup\Contracts\WakeupSweeper;
use CoreX\Wakeup\Data\WakeupDelivery;
use CoreX\Wakeup\Data\WakeupRequest;
use CoreX\Wakeup\Tests\TestCase;
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
