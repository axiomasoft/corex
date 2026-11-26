<?php

declare(strict_types=1);

namespace App\ProductProof;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Enums\FieldUse;
use CoreX\RecurringTasks\Contracts\TaskRecurrenceCommand;
use CoreX\RecurringTasks\Data\MaterializationReceipt;
use CoreX\RecurringTasks\Data\MaterializationWindow;
use CoreX\RecurringTasks\Data\SeriesReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class MaintenanceReceipts implements TaskRecurrenceCommand
{
    public function __construct(private TaskRecurrenceCommand $owner) {}

    public function materialize(DataScope $scope, SeriesReference $series, MaterializationWindow $window, WriteToken $token): MaterializationReceipt
    {
        $db = DB::connection();
        resolve(CurrentWorkScope::class)->assert($scope, $db);

        return $db->transaction(function () use ($scope, $series, $window, $token, $db): MaterializationReceipt {
            $record = new RecordRef('task-series', $series->id);

            if (! resolve(DataScopeAuthorizer::class)->authorize(scope: $scope, record: $record, fields: new FieldSelection([]), use: FieldUse::Write)->allowed) {
                throw new AuthorizationException('The current fixture actor cannot materialize this equipment series.');
            }

            $receipt = $this->owner->materialize(scope: $scope, series: $series, window: $window, token: $token);
            $receiptJson = json_encode($receipt, JSON_THROW_ON_ERROR);
            foreach ($receipt->taskRefs as $task) {
                $db->table('installed_equipment_task_receipts')->insertOrIgnore([
                    'series_id' => $series->id,
                    'workspace_id' => $scope->workspaceId,
                    'task_id' => $task->id,
                    'receipt' => $receiptJson,
                    'digest' => $receipt->pageDigest,
                    'operation_key' => $token->operationKey,
                ]);
            }

            return $receipt;
        });
    }
}
