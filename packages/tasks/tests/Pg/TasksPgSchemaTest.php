<?php

declare(strict_types=1);

use CoreX\Database\Contracts\SchemaSets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->resetPgSchema();
    expect($this->migrateFixtures())->toBe(0)
        ->and($this->migrateTasks())->toBe(0);
});

it('matches the approved PostgreSQL table, column, constraint and index contract', function (): void {
    $tables = DB::table('information_schema.tables')
        ->where('table_schema', 'public')
        ->where('table_name', 'like', 'tsk_%')
        ->orderBy('table_name')
        ->pluck('table_name')
        ->all();

    expect($tables)->toBe([
        'tsk_projects',
        'tsk_sprints',
        'tsk_stages',
        'tsk_task_targets',
        'tsk_task_watchers',
        'tsk_tasks',
        'tsk_time_logs',
        'tsk_timers',
    ]);

    $columns = Schema::getColumnListing('tsk_tasks');
    sort($columns);
    $expectedColumns = [
        'assignee_id', 'author_id', 'completed_at', 'created_at', 'created_by', 'deleted_at',
        'description', 'due_at', 'estimate_minutes', 'id', 'parent_id', 'position', 'priority',
        'project_id', 'recurrence_occurs_at', 'recurrence_rule_id', 'requires_acceptance',
        'search', 'sprint_id', 'spent_minutes', 'stage_id', 'starts_at', 'status', 'title',
        'updated_at', 'workspace_id',
    ];
    sort($expectedColumns);

    $searchType = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'tsk_tasks')
        ->where('column_name', 'search')
        ->value('udt_name');
    $constraints = DB::table('pg_constraint')
        ->whereIn('conname', [
            'tsk_projects_status_chk',
            'tsk_stages_auto_status_chk',
            'tsk_sprints_status_chk',
            'tsk_sprints_dates_chk',
            'tsk_tasks_status_chk',
            'tsk_tasks_priority_chk',
            'tsk_tasks_completed_chk',
            'tsk_tasks_recurrence_chk',
            'tsk_time_logs_minutes_chk',
            'tsk_time_logs_source_chk',
        ])
        ->count();
    $indexes = DB::table('pg_indexes')
        ->where('schemaname', 'public')
        ->whereIn('indexname', [
            'tsk_projects_ws_ix',
            'tsk_sprints_active_uq',
            'tsk_tasks_assignee_ix',
            'tsk_tasks_kanban_ix',
            'tsk_tasks_due_ix',
            'tsk_tasks_sprint_ix',
            'tsk_tasks_search_ix',
            'tsk_tasks_review_ix',
            'tsk_tt_entity_ix',
            'tsk_tl_task_ix',
            'tsk_tl_user_ix',
        ])
        ->count();

    expect($columns)->toBe($expectedColumns)
        ->and($searchType)->toBe('tsvector')
        ->and($constraints)->toBe(10)
        ->and($indexes)->toBe(11);
})->group('pg');

it('refuses to rewrite a non-empty legacy Tasks schema', function (): void {
    $this->resetPgSchema();
    expect($this->migrateFixtures())->toBe(0);

    $legacyFiles = [
        '2026_06_14_000200_create_tsk_processes_table.php',
        '2026_06_14_000210_create_tsk_stages_table.php',
        '2026_06_14_000220_create_tsk_projects_table.php',
        '2026_06_14_000230_create_tsk_tasks_table.php',
        '2026_06_14_000240_create_tsk_task_participants_table.php',
        '2026_06_14_000250_create_tsk_project_participants_table.php',
    ];

    foreach ($legacyFiles as $file) {
        expect($this->migrateFile(app(SchemaSets::class)->get('axiomasoft/corex-tasks:tenant')->path.'/'.$file))->toBe(0);
    }

    DB::table('tsk_processes')->insert([
        'id' => (string) Str::uuid7(),
        'name' => 'Legacy',
        'code' => 'legacy',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => $this->migrateFile(
        app(SchemaSets::class)->get('axiomasoft/corex-tasks:tenant')->path.'/2026_09_11_000260_align_tasks_with_approved_schema.php',
    ))->toThrow(RuntimeException::class);

    expect(Schema::hasTable('tsk_processes'))->toBeTrue()
        ->and(Schema::hasTable('tsk_sprints'))->toBeFalse();
})->group('pg');
