<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            'tsk_projects_status_ck',
            'tsk_stages_auto_status_ck',
            'tsk_sprints_status_ck',
            'tsk_sprints_dates_ck',
            'tsk_tasks_status_ck',
            'tsk_tasks_priority_ck',
            'tsk_tasks_completed_ck',
            'tsk_tasks_recurrence_ck',
            'tsk_time_logs_minutes_ck',
            'tsk_time_logs_source_ck',
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
            'tsk_task_targets_entity_ix',
            'tsk_time_logs_task_ix',
            'tsk_time_logs_user_ix',
        ])
        ->count();

    expect($columns)->toBe($expectedColumns)
        ->and($searchType)->toBe('tsvector')
        ->and($constraints)->toBe(10)
        ->and($indexes)->toBe(11);
})->group('pg');
