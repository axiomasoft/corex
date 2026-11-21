<?php

declare(strict_types=1);

use CoreX\Audit\Enums\ActorType;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tasks\Access\Contracts\TaskPlanningAccess;
use CoreX\Tasks\Planning\Contracts\TaskPlanning;
use CoreX\Tasks\Planning\Data\PlanningToken;
use CoreX\Tasks\Planning\Internal\PlanningHasher;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Internal\Models\Task;
use CoreX\Tasks\Time\Data\TimeLogDraft;
use CoreX\Tasks\Time\Enums\TimeLogSource;
use CoreX\Tasks\Time\Internal\Models\TimeLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

beforeEach(function (): void {
    $this->resetPgSchema();
    expect($this->migrateFixtures())->toBe(0)
        ->and($this->migrateTasks())->toBe(0);

    $this->establishWorkScope();

    $modules = Mockery::mock(ModuleRegistry::class, function (MockInterface $mock): void {
        $mock->shouldReceive('isActive')->byDefault()->andReturnTrue();
    });
    app()->instance(ModuleRegistry::class, $modules);

    $planningAccess = Mockery::mock(TaskPlanningAccess::class);
    $planningAccess->shouldReceive('authorize')->byDefault()->andReturnNull();
    app()->instance(TaskPlanningAccess::class, $planningAccess);

    $this->workspaceId = (string) Str::uuid7();
    $this->userId = (string) Str::uuid7();
    DB::table('wsp_workspaces')->insert(['id' => $this->workspaceId, 'name' => 'Main']);
    DB::table('users')->insert(['id' => $this->userId, 'name' => 'Owner']);
    $this->actor = new ActorData(type: ActorType::User, id: $this->userId);
    $this->context = resolve(TenantContextResolver::class)->current();
    $this->task = Task::query()->create([
        'workspace_id' => $this->workspaceId,
        'title' => 'Concurrent',
        'created_by' => JsonDocuments::decodeTree($this->actor->toArray()),
        'spent_minutes' => 0,
    ]);
});

it('serializes concurrent time-log totals and deduplicates repeated keys', function (): void {
    $draftA = new TimeLogDraft(
        taskId: (string) $this->task->getKey(),
        userId: $this->userId,
        minutes: 20,
        spentOn: '2026-09-17',
        comment: null,
        source: TimeLogSource::Manual,
        actor: $this->actor,
    );
    $draftB = new TimeLogDraft(
        taskId: (string) $this->task->getKey(),
        userId: $this->userId,
        minutes: 15,
        spentOn: '2026-09-17',
        comment: null,
        source: TimeLogSource::Manual,
        actor: $this->actor,
    );
    $tokenA = new PlanningToken(
        requestKey: 'prim-05-a',
        payloadHash: PlanningHasher::timeLog($draftA, $this->context),
    );
    $tokenB = new PlanningToken(
        requestKey: 'prim-05-b',
        payloadHash: PlanningHasher::timeLog($draftB, $this->context),
    );
    $command = resolve(TaskPlanning::class);

    $command->recordTime($draftA, $tokenA);
    $command->recordTime($draftB, $tokenB);
    $command->recordTime($draftA, $tokenA);

    expect(Task::query()->findOrFail($this->task->getKey())->spent_minutes)->toBe(35)
        ->and(TimeLog::query()->count())->toBe(2)
        ->and(DB::table('corex_task_operations')->count())->toBe(2);
})->group('pg');
