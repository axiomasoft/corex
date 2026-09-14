<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates exactly the eight approved Tasks tables', function (): void {
    $expected = [
        'tsk_projects',
        'tsk_sprints',
        'tsk_stages',
        'tsk_tasks',
        'tsk_task_targets',
        'tsk_task_watchers',
        'tsk_time_logs',
        'tsk_timers',
    ];

    foreach ($expected as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }

    expect(Schema::hasTable('tsk_processes'))->toBeFalse()
        ->and(Schema::hasTable('tsk_task_participants'))->toBeFalse()
        ->and(Schema::hasTable('tsk_project_participants'))->toBeFalse();
});

it('does not add columns outside the approved task contract', function (): void {
    $columns = Schema::getColumnListing('tsk_tasks');
    sort($columns);

    $expected = [
        'assignee_id', 'author_id', 'completed_at', 'created_at', 'created_by', 'deleted_at',
        'description', 'due_at', 'estimate_minutes', 'id', 'parent_id', 'position', 'priority',
        'project_id', 'recurrence_occurs_at', 'recurrence_rule_id', 'requires_acceptance',
        'search', 'sprint_id', 'spent_minutes', 'stage_id', 'starts_at', 'status', 'title',
        'updated_at', 'workspace_id',
    ];
    sort($expected);

    expect($columns)->toBe($expected);
});
