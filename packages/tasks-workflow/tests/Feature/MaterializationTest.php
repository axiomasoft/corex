<?php

declare(strict_types=1);

use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\RecurrenceOverride;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Scheduling\Data\WakeupDelivery;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Project\Internal\Models\Project;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Internal\Models\Stage;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Data\MaterializationWindow;
use CoreX\TasksWorkflow\Data\SeriesReference;
use CoreX\TasksWorkflow\Exceptions\StaleMaterialization;
use CoreX\TasksWorkflow\Exceptions\UnsupportedMaterialization;
use CoreX\TasksWorkflow\Internal\MaterializationHasher;
use CoreX\TasksWorkflow\Internal\MaterializationWakeupHandler;
use CoreX\TasksWorkflow\Internal\ScopeConnectionGuard;
use CoreX\TasksWorkflow\Tests\Support\SeriesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->restoreEpoch = str_repeat('a', 64);
    $this->workspaceId = (string) Str::uuid7();
    $this->userId = (string) Str::uuid7();
    DB::table('wsp_workspaces')->insert(['id' => $this->workspaceId, 'name' => 'Main']);
    DB::table('users')->insert(['id' => $this->userId, 'name' => 'Owner']);
    $this->actor = new ActorData(type: ActorType::User, id: $this->userId);
    $this->project = Project::query()->create([
        'workspace_id' => $this->workspaceId,
        'name' => 'Launch',
        'created_by' => JsonDocuments::decodeTree($this->actor->toArray()),
    ]);
    $this->stage = Stage::query()->create([
        'project_id' => $this->project->getKey(),
        'code' => 'todo',
        'name' => ['ru' => 'К работе'],
    ]);
    $this->template = new TaskDraft(
        workspaceId: $this->workspaceId,
        title: 'Recurring maintenance',
        actor: $this->actor,
        projectId: (string) $this->project->getKey(),
        stageId: (string) $this->stage->getKey(),
    );
});

function matScope(object $test): DataScope
{
    return DataScope::fromTenantContext(
        context: app(TenantContextResolver::class)->current(),
        connectionIdentity: ScopeConnectionGuard::identity(DB::connection()),
        actorId: $test->userId,
        restoreEpoch: $test->restoreEpoch,
    );
}

function matToken(object $test, SeriesReference $series, MaterializationWindow $window, string $operationKey = 'mat-op-1'): WriteToken
{
    $scope = matScope($test);

    return new WriteToken(
        operationKey: $operationKey,
        payloadHash: MaterializationHasher::payload($scope, $series, $window),
        expectedRecordVersion: $series->immutableRevision,
    );
}

it('MAT-01 deduplicates the same occurrence key through SQL uniqueness', function (): void {
    $set = SeriesSeeder::dailySet('series-mat-01', 'rev-1', 3);
    $series = SeriesSeeder::seed('series-mat-01', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-10', pageLimit: 10);
    $materializer = app(TaskRecurrenceCommand::class);

    $first = $materializer->materialize(matScope($this), $series, $window, matToken($this, $series, $window, 'mat-01-a'));
    $second = $materializer->materialize(matScope($this), $series, $window, matToken($this, $series, $window, 'mat-01-b'));

    expect($first->unknown)->toBeFalse()
        ->and($first->conflict)->toBe(0)
        ->and($first->created)->toBe(3)
        ->and($second->noop)->toBe(3)
        ->and($second->created)->toBe(0)
        ->and(Task::query()->count())->toBe(3)
        ->and(DB::table('corex_task_occurrences')->count())->toBe(3);
});

it('MAT-02 replays the same operation key with the stored receipt', function (): void {
    $set = SeriesSeeder::dailySet('series-mat-02', 'rev-1', 2);
    $series = SeriesSeeder::seed('series-mat-02', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $token = matToken($this, $series, $window, 'mat-02-op');
    $materializer = app(TaskRecurrenceCommand::class);

    $first = $materializer->materialize(matScope($this), $series, $window, $token);
    $replay = $materializer->materialize(matScope($this), $series, $window, $token);

    expect($replay->pageDigest)->toBe($first->pageDigest)
        ->and($replay->created)->toBe(0)
        ->and(Task::query()->count())->toBe(2);
});

it('MAT-03 stops at a partial budget without advancing beyond the contiguous prefix', function (): void {
    $set = SeriesSeeder::dailySet('series-mat-03', 'rev-1', 10);
    $series = SeriesSeeder::seed('series-mat-03', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-30', pageLimit: 2, maxSeconds: 5);
    $receipt = app(TaskRecurrenceCommand::class)->materialize(
        matScope($this),
        $series,
        $window,
        matToken($this, $series, $window, 'mat-03-op'),
    );

    expect($receipt->created)->toBeLessThanOrEqual(2)
        ->and(Task::query()->count())->toBe($receipt->created)
        ->and($receipt->committedPrefix)->not->toBe('2026-09-30');
});

it('MAT-04 refuses materialization after retirement while keeping committed tasks', function (): void {
    $set = SeriesSeeder::dailySet('series-mat-04', 'rev-1', 2);
    $series = SeriesSeeder::seed('series-mat-04', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    app(TaskRecurrenceCommand::class)->materialize(matScope($this), $series, $window, matToken($this, $series, $window, 'mat-04-a'));

    DB::table('corex_task_series_state')->where('series_key', 'series-mat-04')->update(['retired' => true]);
    $receipt = app(TaskRecurrenceCommand::class)->materialize(matScope($this), $series, $window, matToken($this, $series, $window, 'mat-04-b'));

    expect($receipt->retired)->toBeTrue()
        ->and(Task::query()->count())->toBe(2);
});

it('MAT-05 keeps the original occurrence key across moved overrides and revision cutover', function (): void {
    $set = RecurrenceSet::create(
        seriesId: 'series-mat-05',
        immutableRevision: 'rev-1',
        start: new DateStart(valueType: 'DATE', nominalStart: '2026-09-01', tzid: null),
        rrule: 'FREQ=DAILY;COUNT=3',
        overrides: [
            new RecurrenceOverride(originalRecurrenceId: '2026-09-02', effectiveStart: '2026-09-03', cancelled: false),
        ],
    );
    $series = SeriesSeeder::seed('series-mat-05', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-10');
    app(TaskRecurrenceCommand::class)->materialize(matScope($this), $series, $window, matToken($this, $series, $window, 'mat-05-a'));

    $occurrence = DB::table('corex_task_occurrences')->where('occurrence_key', '!=', '')->first();
    expect($occurrence)->not->toBeNull();

    $newSet = RecurrenceSet::create(
        seriesId: 'series-mat-05',
        immutableRevision: 'rev-2',
        start: new DateStart(valueType: 'DATE', nominalStart: '2026-09-01', tzid: null),
        rrule: 'FREQ=DAILY;COUNT=3',
    );
    SeriesSeeder::seed('series-mat-05', $newSet, $this->template, $this->restoreEpoch);
    DB::table('corex_task_series_state')->where('series_key', 'series-mat-05')->update([
        'active_revision' => 'rev-2',
        'generation' => 1,
    ]);

    $newSeries = new SeriesReference('series-mat-05', 'rev-2', $newSet->definitionHash);
    app(TaskRecurrenceCommand::class)->materialize(matScope($this), $newSeries, $window, matToken($this, $newSeries, $window, 'mat-05-b'));

    expect(DB::table('corex_task_occurrences')->where('immutable_revision', 'rev-1')->count())->toBe(3)
        ->and(DB::table('corex_task_occurrences')->where('immutable_revision', 'rev-2')->count())->toBeGreaterThan(0);
});

it('MAT-08 denies stale restore epochs and re-checks access before writes', function (): void {
    $set = SeriesSeeder::dailySet('series-mat-08', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-mat-08', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $staleScope = DataScope::fromTenantContext(
        context: app(TenantContextResolver::class)->current(),
        connectionIdentity: ScopeConnectionGuard::identity(DB::connection()),
        actorId: $this->userId,
        restoreEpoch: str_repeat('b', 64),
    );

    expect(fn () => app(TaskRecurrenceCommand::class)->materialize(
        $staleScope,
        $series,
        $window,
        new WriteToken('mat-08', MaterializationHasher::payload($staleScope, $series, $window), $series->immutableRevision),
    ))->toThrow(StaleMaterialization::class);

    $denyAccess = Mockery::mock(TaskAccess::class);
    $denyAccess->shouldReceive('authorizeCreate')->once()->andThrow(new AuthorizationException);
    $this->app->instance(TaskAccess::class, $denyAccess);
    $this->app->forgetInstance(TaskRecurrenceCommand::class);
    $this->app->forgetScopedInstances();

    expect(fn () => app(TaskRecurrenceCommand::class)->materialize(
        matScope($this),
        $series,
        $window,
        matToken($this, $series, $window, 'mat-08-deny'),
    ))->toThrow(AuthorizationException::class);
});

it('MAT-09 keeps boxed sqlite materialization off root credentials and queue assumptions', function (): void {
    config(['queue.default' => 'sync']);
    $set = SeriesSeeder::dailySet('series-mat-09', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-mat-09', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $receipt = app(TaskRecurrenceCommand::class)->materialize(
        matScope($this),
        $series,
        $window,
        matToken($this, $series, $window, 'mat-09-op'),
    );

    expect($receipt->unknown)->toBeFalse()
        ->and(config('queue.default'))->toBe('sync')
        ->and(DB::connection()->getDriverName())->toBe('sqlite');
});

it('MAT-10 encodes a complete wakeup payload and preserves the materialization window', function (): void {
    $set = SeriesSeeder::dailySet('series-mat-10', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-mat-10', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05', aheadDays: 14, pageLimit: 25, maxSeconds: 3);

    $payload = MaterializationHasher::wakeupPayload(series: $series, window: $window);
    $decoded = MaterializationHasher::decodeWakeupPayload($payload);

    expect($payload)->toMatchArray([
        'seriesId' => 'series-mat-10',
        'immutableRevision' => 'rev-1',
        'definitionHash' => $series->definitionHash,
        'start' => '2026-09-01',
        'end' => '2026-09-05',
        'aheadDays' => 14,
        'pageLimit' => 25,
        'maxSeconds' => 3,
    ])
        ->and($decoded['series']->definitionHash)->toBe($series->definitionHash)
        ->and($decoded['window']->start)->toBe('2026-09-01')
        ->and($decoded['window']->end)->toBe('2026-09-05')
        ->and($decoded['window']->aheadDays)->toBe(14)
        ->and($decoded['window']->pageLimit)->toBe(25)
        ->and($decoded['window']->maxSeconds)->toBe(3);
});

it('MAT-11 materializes a valid stamped wakeup and stays idempotent on duplicate delivery', function (): void {
    $set = SeriesSeeder::dailySet('series-mat-11', 'rev-1', 2);
    $series = SeriesSeeder::seed('series-mat-11', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $handler = app(MaterializationWakeupHandler::class);
    $delivery = wakeupDelivery(
        context: app(TenantContextResolver::class)->current(),
        payload: MaterializationHasher::wakeupPayload(series: $series, window: $window),
        requestKey: 'wakeup-mat-11',
    );

    $handler->handle($delivery);
    $handler->handle($delivery);

    expect(Task::query()->count())->toBe(2)
        ->and(DB::table('corex_task_occurrences')->count())->toBe(2);
});

it('MAT-12 fails incomplete or tampered wakeup payloads without writing', function (): void {
    $set = SeriesSeeder::dailySet('series-mat-12', 'rev-1', 1);
    $series = SeriesSeeder::seed('series-mat-12', $set, $this->template, $this->restoreEpoch);
    $window = new MaterializationWindow(start: '2026-09-01', end: '2026-09-05');
    $handler = app(MaterializationWakeupHandler::class);
    $context = app(TenantContextResolver::class)->current();
    $complete = MaterializationHasher::wakeupPayload(series: $series, window: $window);

    expect(fn () => $handler->handle(wakeupDelivery(
        context: $context,
        payload: ['seriesId' => $series->id, 'immutableRevision' => 'rev-1'],
        requestKey: 'wakeup-mat-12-missing',
    )))->toThrow(StaleMaterialization::class);

    expect(fn () => MaterializationHasher::decodeWakeupPayload([]))
        ->toThrow(UnsupportedMaterialization::class);

    $tampered = $complete;
    $tampered['definitionHash'] = str_repeat('f', 64);

    expect(fn () => $handler->handle(wakeupDelivery(
        context: $context,
        payload: $tampered,
        requestKey: 'wakeup-mat-12-tamper',
    )))->toThrow(StaleMaterialization::class);

    expect(Task::query()->count())->toBe(0)
        ->and(DB::table('corex_task_occurrences')->count())->toBe(0);
});

/**
 * @param  array<array-key, mixed>  $payload
 */
function wakeupDelivery(object $context, array $payload, string $requestKey): WakeupDelivery
{
    return new WakeupDelivery(
        request: new WakeupRequest(
            context: $context,
            kind: MaterializationHasher::HANDLER_KIND,
            requestKey: $requestKey,
            fireAt: new DateTimeImmutable('2026-09-16T10:00:00Z'),
            window: null,
            payload: JsonDocuments::capture($payload),
        ),
        generation: 1,
        attempt: 1,
    );
}
