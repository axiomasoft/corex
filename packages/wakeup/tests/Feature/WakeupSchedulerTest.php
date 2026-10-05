<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\CoreServiceProvider;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Time\Laravel\SystemClock;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Wakeup\Contracts\DueWakeupSweeper;
use CoreX\Wakeup\Contracts\WakeupScheduleHint;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupDelivery;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Scheduling\Data\WakeupWindow;
use CoreX\Wakeup\Internal\SqlWakeupScheduler;
use CoreX\Wakeup\Internal\SweepRunner;
use CoreX\Wakeup\Internal\WakeupHandlerRegistry;
use CoreX\Wakeup\Internal\WakeupOptions;
use CoreX\Wakeup\Tests\TestCase;
use CoreX\Wakeup\WakeupModule;
use CoreX\Wakeup\WakeupServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;

uses(TestCase::class);

final class WakeupTestHandler implements DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void
    {
        DB::table('wakeup_effects')->insertOrIgnore(['request_key' => $delivery->request->requestKey]);
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

final class PayloadRecordingWakeupHandler implements DueWakeupHandler
{
    /** @var list<array<array-key, mixed>> */
    public array $payloads = [];

    public function handle(WakeupDelivery $delivery): void
    {
        $this->payloads[] = JsonDocuments::map($delivery->request->payload);
    }
}

/**
 * @param  array<array-key, mixed>  $payload
 */
function insertLegacyWakeup(string $requestKey, array $payload, string $kind = 'test.reminder'): void
{
    $now = new DateTimeImmutable('-1 minute');

    DB::table('sys_wakeup_requests')->insert([
        'id' => (string) Str::uuid7(),
        'workspace_scope_key' => 'account',
        'kind' => $kind,
        'request_key' => $requestKey,
        'request_digest' => hash('sha256', $requestKey),
        'fire_at' => $now,
        'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
        'status' => 'pending',
        'generation' => 0,
        'attempt' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

it('persists idempotent requests and completes the local handler once', function (): void {
    app()['config']->set('corex-wakeup.handlers', ['test.reminder' => ['handler' => WakeupTestHandler::class, 'version' => 'test/1']]);
    app()->forgetInstance(WakeupOptions::class);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    $scheduler = app(WakeupScheduler::class);
    $request = new WakeupRequest($context, 'test.reminder', 'reminder-1', new DateTimeImmutable('-1 minute'), null, JsonDocuments::capture(['message' => 'wake']));

    expect($scheduler->schedule($request)->status)->toBe('created')
        ->and($scheduler->schedule($request)->status)->toBe('existing');

    $result = app(DueWakeupSweeper::class)->sweep($context, 10);

    expect($result->completed)->toBe(1)
        ->and(DB::table('wakeup_effects')->where('request_key', 'reminder-1')->count())->toBe(1);
});

it('stores a versioned envelope and exposes only business data to the handler', function (): void {
    $handler = new PayloadRecordingWakeupHandler;
    app()->instance(PayloadRecordingWakeupHandler::class, $handler);
    app()['config']->set('corex-wakeup.handlers', [
        'test.reminder' => [
            'handler' => PayloadRecordingWakeupHandler::class,
            'version' => 'reminder/1',
            'accept_legacy_unstamped' => false,
        ],
    ]);
    app()->forgetInstance(WakeupOptions::class);
    app()->forgetInstance(WakeupHandlerRegistry::class);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    $request = new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'versioned-envelope',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: JsonDocuments::capture(['message' => 'wake']),
    );

    expect(app(WakeupScheduler::class)->schedule($request)->status)->toBe('created');

    $stored = json_decode(
        (string) DB::table('sys_wakeup_requests')->where('request_key', 'versioned-envelope')->value('payload'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($stored)->toBe([
        '_corex' => [
            'schema' => 'wakeup-payload/2',
            'extension' => [
                'point' => 'wakeup.handler',
                'id' => 'test.reminder',
                'version' => 'reminder/1',
                'registryFingerprint' => 'module-free',
            ],
        ],
        'data' => ['message' => 'wake'],
    ]);

    expect(app(DueWakeupSweeper::class)->sweep($context, 10)->completed)->toBe(1)
        ->and($handler->payloads)->toBe([['message' => 'wake']]);
});

it('keeps exact replay valid when only registry provenance changes', function (): void {
    $handler = new PayloadRecordingWakeupHandler;
    $definition = new WakeupHandlerDefinition(
        kind: 'test.reminder',
        version: 'reminder/1',
        handler: PayloadRecordingWakeupHandler::class,
    );
    $registryA = WakeupHandlerRegistry::compile(
        definitions: [$definition],
        resolve: static fn (string $class): DueWakeupHandler => $handler,
        registryFingerprint: 'registry-a',
    );
    $registryB = WakeupHandlerRegistry::compile(
        definitions: [$definition],
        resolve: static fn (string $class): DueWakeupHandler => $handler,
        registryFingerprint: 'registry-b',
    );
    $context = app(TenantContextResolver::class)->current();
    $request = new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'fingerprint-drift',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: JsonDocuments::capture(['message' => 'wake']),
    );
    $schedulerA = new SqlWakeupScheduler(
        connection: DB::connection(),
        contexts: app(TenantContextResolver::class),
        handlers: $registryA,
        hints: [],
        clock: app(ClockInterface::class),
    );
    $schedulerB = new SqlWakeupScheduler(
        connection: DB::connection(),
        contexts: app(TenantContextResolver::class),
        handlers: $registryB,
        hints: [],
        clock: app(ClockInterface::class),
    );
    $sweeper = new SweepRunner(
        connection: DB::connection(),
        contexts: app(TenantContextResolver::class),
        handlers: $registryB,
        dueSources: [],
        leaseSeconds: 60,
        maxAttempts: 5,
        clock: app(ClockInterface::class),
    );

    expect($schedulerA->schedule($request)->status)->toBe('created')
        ->and($schedulerB->schedule($request)->status)->toBe('existing')
        ->and($sweeper->sweep($context, 10)->completed)->toBe(1)
        ->and($handler->payloads)->toBe([['message' => 'wake']]);
});

it('retains the legacy digest algorithm for unstamped existing rows', function (): void {
    app()['config']->set('corex-wakeup.handlers', [
        'test.reminder' => [
            'handler' => WakeupTestHandler::class,
            'version' => 'reminder/1',
        ],
    ]);
    app()->forgetInstance(WakeupOptions::class);
    app()->forgetInstance(WakeupHandlerRegistry::class);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    $fireAt = new DateTimeImmutable('2026-06-01T12:00:00Z');
    $payload = ['message' => 'legacy'];
    $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
    $digest = hash('sha256', 'wakeup/1'."\0test.reminder\0legacy-existing\0".$fireAt->format('Y-m-d\\TH:i:s.u\\Z')."\0".$encoded);
    insertLegacyWakeup(requestKey: 'legacy-existing', payload: $payload);
    DB::table('sys_wakeup_requests')->where('request_key', 'legacy-existing')->update([
        'request_digest' => $digest,
        'fire_at' => $fireAt,
    ]);

    $result = app(WakeupScheduler::class)->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'legacy-existing',
        fireAt: $fireAt,
        window: null,
        payload: JsonDocuments::capture($payload),
    ));

    expect($result->status)->toBe('existing')
        ->and(DB::table('sys_wakeup_requests')->count())->toBe(1);
});

it('quarantines missing and mismatched stamps without invocation or retry', function (): void {
    $handler = new PayloadRecordingWakeupHandler;
    app()->instance(PayloadRecordingWakeupHandler::class, $handler);
    app()['config']->set('corex-wakeup.handlers', [
        'test.reminder' => [
            'handler' => PayloadRecordingWakeupHandler::class,
            'version' => 'reminder/2',
        ],
    ]);
    app()->forgetInstance(WakeupOptions::class);
    app()->forgetInstance(WakeupHandlerRegistry::class);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    insertLegacyWakeup(requestKey: 'missing-stamp', payload: ['message' => 'legacy']);
    $registry = app(WakeupHandlerRegistry::class);
    $definition = $registry->definition('test.reminder');
    $mismatched = $registry->envelope($definition, ['message' => 'old']);
    $mismatched['_corex']['extension']['version'] = 'reminder/1';
    insertLegacyWakeup(requestKey: 'mismatched-stamp', payload: $mismatched);

    $result = app(DueWakeupSweeper::class)->sweep($context, 10);
    $rows = DB::table('sys_wakeup_requests')->orderBy('request_key')->get()->keyBy('request_key');

    expect($result->failed)->toBe(2)
        ->and($handler->payloads)->toBe([])
        ->and($rows['missing-stamp']->failure_code)->toBe('handler-stamp-missing')
        ->and($rows['mismatched-stamp']->failure_code)->toBe('handler-version-mismatch')
        ->and($rows['missing-stamp']->attempt)->toBe(5)
        ->and($rows['mismatched-stamp']->attempt)->toBe(5)
        ->and($rows['missing-stamp']->retry_at)->toBeNull()
        ->and($rows['mismatched-stamp']->retry_at)->toBeNull();
});

it('admits unstamped rows only for the exact definition opt-in', function (): void {
    $handler = new PayloadRecordingWakeupHandler;
    app()->instance(PayloadRecordingWakeupHandler::class, $handler);
    app()['config']->set('corex-wakeup.handlers', [
        'test.reminder' => [
            'handler' => PayloadRecordingWakeupHandler::class,
            'version' => 'reminder/1',
            'accept_legacy_unstamped' => true,
        ],
    ]);
    app()->forgetInstance(WakeupOptions::class);
    app()->forgetInstance(WakeupHandlerRegistry::class);
    app()->forgetScopedInstances();
    insertLegacyWakeup(requestKey: 'legacy-opt-in', payload: ['message' => 'legacy']);

    $this->artisan('corex:wakeup:validate-stamps')
        ->expectsOutputToContain('legacy-admitted')
        ->expectsOutputToContain('no rows were changed')
        ->assertExitCode(1);

    $result = app(DueWakeupSweeper::class)->sweep(app(TenantContextResolver::class)->current(), 10);

    expect($result->completed)->toBe(1)
        ->and($handler->payloads)->toBe([['message' => 'legacy']]);
});

it('reports incompatible pending stamps without mutating rows', function (): void {
    app()['config']->set('corex-wakeup.handlers', [
        'test.reminder' => [
            'handler' => WakeupTestHandler::class,
            'version' => 'reminder/1',
        ],
    ]);
    app()->forgetInstance(WakeupOptions::class);
    app()->forgetInstance(WakeupHandlerRegistry::class);
    insertLegacyWakeup(requestKey: 'preflight-legacy', payload: ['message' => 'legacy']);
    $before = (array) DB::table('sys_wakeup_requests')->where('request_key', 'preflight-legacy')->first();

    $this->artisan('corex:wakeup:validate-stamps')
        ->expectsOutputToContain('handler-stamp-missing')
        ->expectsOutputToContain('no rows were changed')
        ->assertExitCode(1);

    $after = (array) DB::table('sys_wakeup_requests')->where('request_key', 'preflight-legacy')->first();

    expect($after)->toBe($before);
});

it('rejects scalar handler config because handler and version are explicit', function (): void {
    expect(fn () => WakeupOptions::fromArray(['handlers' => ['test.reminder' => WakeupTestHandler::class]]))
        ->toThrow(InvalidConfiguration::class, 'handlers.test.reminder: requires handler, version');
});

it('persists a retryable handler failure without reusing its generation', function (): void {
    app()['config']->set('corex-wakeup.handlers', ['test.failure' => ['handler' => FailingWakeupTestHandler::class, 'version' => 'test/1']]);
    app()->forgetInstance(WakeupOptions::class);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    app(WakeupScheduler::class)->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.failure',
        requestKey: 'failure-1',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: JsonDocuments::capture([]),
    ));

    $result = app(DueWakeupSweeper::class)->sweep($context, 10);
    $row = DB::table('sys_wakeup_requests')->where('request_key', 'failure-1')->first();

    expect($result->retried)->toBe(1)
        ->and($row->status)->toBe('failed')
        ->and($row->generation)->toBe(1)
        ->and($row->attempt)->toBe(1)
        ->and($row->retry_at)->not->toBeNull();
});

it('rejects unsafe request data before writing a durable request', function (): void {
    app()['config']->set('corex-wakeup.handlers', ['test.reminder' => ['handler' => WakeupTestHandler::class, 'version' => 'test/1']]);
    app()->forgetInstance(WakeupOptions::class);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();

    expect(fn (): WakeupRequest => new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'not-utc',
        fireAt: new DateTimeImmutable('2026-09-15T12:00:00+03:00'),
        window: null,
        payload: JsonDocuments::capture([]),
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(WakeupScheduler::class)->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'float-payload',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: JsonDocuments::capture(['amount' => 1.5]),
    )))->toThrow(RuntimeException::class);

    expect(DB::table('sys_wakeup_requests')->count())->toBe(0);
});

it('keeps cancellation keys unambiguous across registered kinds and dispatches a best-effort hint', function (): void {
    app()['config']->set('corex-wakeup.handlers', [
        'test.reminder' => ['handler' => WakeupTestHandler::class, 'version' => 'test/1'],
        'test.secondary' => ['handler' => WakeupTestHandler::class, 'version' => 'test/1'],
    ]);
    app()->forgetInstance(WakeupOptions::class);
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
        payload: JsonDocuments::capture([]),
    ))->status)->toBe('created');

    expect($scheduler->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.secondary',
        requestKey: 'shared-key',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: JsonDocuments::capture([]),
    ))->status)->toBe('conflict')
        ->and($hint->accounts)->toBe([$context->account->id])
        ->and(DB::table('sys_wakeup_requests')->count())->toBe(1);
});

it('makes cancellation outcomes explicit without depending on a root hint', function (): void {
    app()['config']->set('corex-wakeup.handlers', ['test.reminder' => ['handler' => WakeupTestHandler::class, 'version' => 'test/1']]);
    app()->forgetInstance(WakeupOptions::class);
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
        payload: JsonDocuments::capture(['nested' => ['b' => 2, 'a' => true]]),
    ));

    expect($scheduler->cancel(context: $context, requestKey: 'cancelled'))->status->toBe('cancelled')
        ->and($scheduler->cancel(context: $context, requestKey: 'cancelled'))->status->toBe('already-cancelled');

    $scheduler->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.reminder',
        requestKey: 'completed',
        fireAt: new DateTimeImmutable('-1 minute'),
        window: null,
        payload: JsonDocuments::capture([]),
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
        payload: JsonDocuments::capture([]),
    );

    expect(fn () => app(WakeupScheduler::class)->schedule($request))->toThrow(RuntimeException::class)
        ->and(fn (): WakeupWindow => new WakeupWindow(
            start: new DateTimeImmutable('2026-09-15T12:00:00+03:00'),
            end: new DateTimeImmutable('2026-09-15T13:00:00+03:00'),
            period: 'daily',
        ))->toThrow(InvalidArgumentException::class)
        ->and((new WakeupModule)->manifest()->composerName)->toBe('axiomasoft/corex-wakeup');
});

it('computes handler backoff from the injected clock', function (): void {
    app()['config']->set('corex-wakeup.handlers', ['test.failure' => ['handler' => FailingWakeupTestHandler::class, 'version' => 'test/1']]);
    app()->forgetInstance(WakeupOptions::class);
    $anchor = new DateTimeImmutable('2026-06-01T12:00:00Z');
    $clock = new FrozenClock($anchor);
    app()->instance(ClockInterface::class, $clock);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    $scheduler = app(WakeupScheduler::class);
    $sweeper = app(DueWakeupSweeper::class);

    $scheduler->schedule(new WakeupRequest(
        context: $context,
        kind: 'test.failure',
        requestKey: 'backoff-steps',
        fireAt: $anchor->sub(new DateInterval('PT1M')),
        window: null,
        payload: JsonDocuments::capture([]),
    ));

    $expectedOffsets = [2, 4, 8, 16];

    foreach ($expectedOffsets as $offsetSeconds) {
        $sweeper->sweep($context, 10);
        $row = DB::table('sys_wakeup_requests')->where('request_key', 'backoff-steps')->first();
        $expectedRetry = $clock->now()->add(new DateInterval("PT{$offsetSeconds}S"));

        expect($row->retry_at)->toBe($expectedRetry->format('Y-m-d H:i:s'))
            ->and($row->status)->toBe('failed');

        $clock->advance(new DateInterval("PT{$offsetSeconds}S"));
        app()->forgetScopedInstances();
        $sweeper = app(DueWakeupSweeper::class);
    }
});

it('expires a running lease exactly at the clock boundary but not one second later', function (): void {
    app()['config']->set('corex-wakeup.handlers', ['test.reminder' => ['handler' => WakeupTestHandler::class, 'version' => 'test/1']]);
    app()->forgetInstance(WakeupOptions::class);
    app()['config']->set('corex-wakeup.max_attempts', 5);
    app()->forgetInstance(WakeupOptions::class);
    $anchor = new DateTimeImmutable('2026-06-01T12:00:00Z');
    $clock = new FrozenClock($anchor);
    app()->instance(ClockInterface::class, $clock);
    app()->forgetScopedInstances();
    $context = app(TenantContextResolver::class)->current();
    $scope = 'account';

    DB::table('sys_wakeup_requests')->insert([
        'id' => (string) Str::uuid7(),
        'workspace_scope_key' => $scope,
        'kind' => 'test.reminder',
        'request_key' => 'lease-boundary',
        'request_digest' => hash('sha256', 'lease-boundary'),
        'fire_at' => $anchor->sub(new DateInterval('PT1M')),
        'payload' => '{}',
        'status' => 'running',
        'generation' => 1,
        'attempt' => 5,
        'lease_until' => $anchor,
        'created_at' => $anchor,
        'updated_at' => $anchor,
    ]);

    app(DueWakeupSweeper::class)->sweep($context, 10);

    $row = DB::table('sys_wakeup_requests')->where('request_key', 'lease-boundary')->first();

    expect($row->status)->toBe('failed')
        ->and($row->failure_code)->toBe('lease-expired');

    DB::table('sys_wakeup_requests')->where('request_key', 'lease-boundary')->update([
        'status' => 'running',
        'lease_until' => $anchor->add(new DateInterval('PT1S')),
        'failure_code' => null,
    ]);

    app()->forgetScopedInstances();
    app(DueWakeupSweeper::class)->sweep($context, 10);

    expect(DB::table('sys_wakeup_requests')->where('request_key', 'lease-boundary')->value('status'))->toBe('running');
});

it('resolves the scheduler and sweeper from the package clock without a host binding', function (): void {
    expect(app()->getLoadedProviders())->not->toHaveKey(CoreServiceProvider::class);

    unset(app()[ClockInterface::class]);
    app()->forgetScopedInstances();
    (new WakeupServiceProvider(app()))->register();

    $clock = app(ClockInterface::class);

    expect($clock)->toBeInstanceOf(SystemClock::class)
        ->and($clock->now()->getTimezone()->getName())->toBe('UTC')
        ->and(app(WakeupScheduler::class))->toBeInstanceOf(WakeupScheduler::class)
        ->and(app(DueWakeupSweeper::class))->toBeInstanceOf(DueWakeupSweeper::class)
        ->and(app(ClockInterface::class))->toBe($clock);
});

it('preserves a FrozenClock already bound when the wakeup provider registers', function (): void {
    $clock = new FrozenClock(new DateTimeImmutable('2026-03-04T05:06:07Z'));
    app()->instance(ClockInterface::class, $clock);
    app()->forgetScopedInstances();
    (new WakeupServiceProvider(app()))->register();

    expect(app(ClockInterface::class))->toBe($clock);

    app(WakeupScheduler::class);
    app(DueWakeupSweeper::class);

    expect(app(ClockInterface::class))->toBe($clock)
        ->and($clock->now()->format(DateTimeImmutable::ATOM))->toBe('2026-03-04T05:06:07+00:00');
});
