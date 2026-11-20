<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Index\Enums\IndexOutcome;
use RuntimeException;

final class SearchScenarioIndexDriver implements IndexDriver
{
    private bool $paused = false;

    public function __construct(private readonly IndexDriver $actual, private readonly SearchScenarioTrace $trace) {}

    public function submit(IndexBatch $batch): IndexReceipt
    {
        SearchScenarioMeili::register($batch->target);

        $barrier = getenv('COREX_SCENARIO_INDEX_BARRIER');

        if (! $this->paused && $barrier === 'late-submit' && $batch->action === IndexAction::Upsert) {
            $this->paused = true;
            Scenario::barrier('old-task-submission', ['uid' => $batch->target->uid, 'submission' => $batch->submissionId]);
        }

        if (! $this->paused && $barrier === 'during-scan' && $batch->action === IndexAction::Upsert) {
            $this->paused = true;
            Scenario::barrier('scan-submitting', ['uid' => $batch->target->uid, 'submission' => $batch->submissionId]);
        }
        $receipt = $this->actual->submit($batch);
        $this->trace->submissions[] = ['uid' => $batch->target->uid, 'id' => $batch->submissionId, 'receipt' => $receipt->taskId, 'action' => $batch->action->value];

        return $receipt;
    }

    public function lookup(IndexBatch $batch, ?IndexReceipt $receipt): IndexOutcome
    {
        SearchScenarioMeili::assertOwned($batch->target);
        $observed = $this->actual->lookup($batch, $receipt);

        $barrier = getenv('COREX_SCENARIO_INDEX_BARRIER');

        if (! $this->paused && in_array($barrier, ['destination-confirm', 'pending-confirm'], true) && $batch->action === IndexAction::Upsert
            && $observed === IndexOutcome::Confirmed && $receipt !== null && self::throughBarrier($batch)) {
            if ($barrier === 'pending-confirm') {
                return IndexOutcome::Pending;
            }
            $this->paused = true;
            $task = SearchScenarioMeili::task($receipt->taskId);

            if ($task['status'] !== 'succeeded' || $task['indexUid'] !== $batch->target->uid) {
                throw new RuntimeException('The provider task is not the successful destination submission.');
            }
            Scenario::barrier('destination-task-confirmed', ['uid' => $batch->target->uid, 'submission' => $batch->submissionId, 'task' => $task]);
        }

        return $observed;
    }

    private static function throughBarrier(IndexBatch $batch): bool
    {
        $db = Scenario::connection('a');
        $scope = $batch->target->scope;
        $lane = SearchScenarioRebuild::lane($db, $scope->workspaceId ?? throw new RuntimeException('Expected workspace index.'), $scope->restoreEpoch);
        $submission = $db->table('corex_search_submissions')->where(['lane_key' => $lane->lane_key, 'submission_id' => $batch->submissionId])->firstOrFail();

        return $lane->freeze_seq !== null && (int) $submission->last_seq === (int) $lane->freeze_seq;
    }

    public function canary(IndexTarget $target): bool
    {
        SearchScenarioMeili::assertOwned($target);

        return $this->actual->canary($target);
    }
}
