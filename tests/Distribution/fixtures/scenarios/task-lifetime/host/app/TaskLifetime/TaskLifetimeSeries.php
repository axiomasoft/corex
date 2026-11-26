<?php

declare(strict_types=1);

namespace App\TaskLifetime;

use App\Scenario\Scenario;
use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\RecurringTasks\Contracts\TaskRecurrenceCommand;
use CoreX\RecurringTasks\Data\MaterializationWindow;
use CoreX\RecurringTasks\Data\SeriesReference;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;

final class TaskLifetimeSeries
{
    public static function materialize(Scenario $proof, Connection $database, Closure $enterScope): string
    {
        $set = RecurrenceSet::create('installed-task-lifetime-series', 'v1', new DateStart('DATE', '2026-11-20', null), 'FREQ=DAILY;COUNT=1');
        $database->table('corex_task_series')->insert([
            'id' => (string) Str::uuid7(), 'series_key' => $set->seriesId, 'immutable_revision' => $set->immutableRevision,
            'definition_hash' => $set->definitionHash, 'definition' => json_encode($set, JSON_THROW_ON_ERROR),
            'template' => json_encode(['workspaceId' => Scenario::W1, 'title' => 'Installed series task', 'authorId' => Scenario::USER_A], JSON_THROW_ON_ERROR),
            'actor' => json_encode(['type' => 'user', 'id' => Scenario::USER_A], JSON_THROW_ON_ERROR),
            'context_hash' => hash('sha256', Scenario::W1), 'restore_epoch' => str_repeat('a', 64),
        ]);
        $database->table('corex_task_series_state')->insert([
            'series_key' => $set->seriesId, 'active_revision' => $set->immutableRevision, 'restore_epoch' => str_repeat('a', 64),
        ]);
        $database->table('scenario_field_grants')->insert([
            'principal_id' => Scenario::USER_A, 'workspace_id' => Scenario::W1, 'entity' => 'task-series',
            'record_id' => $set->seriesId, 'field_id' => 'record', 'field_use' => 'write',
        ]);

        return $enterScope(static function (DataScope $scope) use ($proof, $database, $set): string {
            $series = new SeriesReference($set->seriesId, $set->immutableRevision, $set->definitionHash);
            $window = new MaterializationWindow(start: '2026-11-20', end: '2026-11-21', aheadDays: 1, pageLimit: 10);
            $digest = hash('sha256', implode("\0", [
                'task-materialization/1', json_encode($scope, JSON_THROW_ON_ERROR), $series->id, $series->immutableRevision,
                $series->definitionHash, $window->start, $window->end, (string) $window->aheadDays, (string) $window->pageLimit, (string) $window->maxSeconds,
            ]));
            $token = new WriteToken('installed-series-window', $digest, $series->immutableRevision);
            $owner = app(TaskRecurrenceCommand::class);
            $first = $owner->materialize($scope, $series, $window, $token);
            $again = $owner->materialize($scope, $series, $window, $token);
            $proof->same(1, $first->created, 'The real installed RecurringTasks command materializes one occurrence');
            $proof->same($first->pageDigest, $again->pageDigest, 'The public materialization command replays the same receipt');
            $proof->same(1, $database->table('corex_task_occurrences')->count(), 'One durable series occurrence exists after replay');
            $proof->same(1, $database->table('corex_task_operations')->where('kind', 'materialize')->count(), 'One durable Tasks materialization receipt exists after replay');
            $proof->same(1, $database->table('tsk_tasks')->count(), 'One normal Task exists after occurrence replay');
            $proof->same($first->taskRefs[0]->id, $again->taskRefs[0]->id, 'The occurrence replay references the same created task');

            return $first->taskRefs[0]->id;
        });
    }
}
