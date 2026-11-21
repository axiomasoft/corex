<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\JsonDocument;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Planning\Data\TaskOperationFence;
use CoreX\Tasks\Planning\Data\TaskOperationIdentity;
use CoreX\Tasks\Planning\Data\TaskOperationKind;
use CoreX\Tasks\Planning\Data\TaskOperationReceipt;
use CoreX\Tasks\Planning\Exceptions\PlanningConflict;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use LogicException;
use Override;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class DatabaseTaskOperationReceipts implements TaskOperationReceipts
{
    public function __construct(private ClockInterface $clock, private CurrentWorkScope $workScope) {}

    #[Override]
    public function find(ConnectionInterface $connection, TaskOperationIdentity $identity): ?TaskOperationReceipt
    {
        $this->workScope->assert($identity->scope, $connection);

        $query = $connection->table('corex_task_operations')->useWritePdo()->where($this->key($identity));

        if ($connection->transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        if ($row === null) {
            return null;
        }

        if ($row->kind !== $identity->kind->value || $row->subject_ref !== $identity->subjectRef) {
            throw PlanningConflict::forRequestKey($identity->requestKey);
        }

        return new TaskOperationReceipt(
            payloadHash: Value::text($row->payload_hash),
            originalFence: new TaskOperationFence(
                deploymentId: Value::text($row->deployment_id),
                bindingId: Value::text($row->binding_id),
                bindingGeneration: (int) Value::text($row->binding_generation),
                accountIncarnation: Value::text($row->account_incarnation),
                restoreEpoch: Value::text($row->restore_epoch),
            ),
            resultRef: new JsonDocument(Value::text($row->result_ref)),
        );
    }

    #[Override]
    public function record(ConnectionInterface $connection, TaskOperationIdentity $identity, TaskOperationReceipt $receipt): void
    {
        if ($connection->transactionLevel() < 1
            || get_object_vars($receipt->originalFence) !== get_object_vars(TaskOperationFence::fromScope($identity->scope))) {
            throw new LogicException('Task operation receipts require the active owner transaction and its original fence.');
        }

        $this->workScope->assert($identity->scope, $connection);
        $fence = $receipt->originalFence;

        try {
            $connection->table('corex_task_operations')->insert($this->key($identity) + [
                'id' => (string) Str::uuid7(),
                'kind' => $identity->kind->value,
                'subject_ref' => $identity->subjectRef,
                'payload_hash' => $receipt->payloadHash,
                'result_ref' => $receipt->resultRef->canonicalJson,
                'deployment_id' => $fence->deploymentId,
                'binding_id' => $fence->bindingId,
                'binding_generation' => $fence->bindingGeneration,
                'restore_epoch' => $fence->restoreEpoch,
                'created_at' => $this->clock->now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The caller must roll back business changes; PostgreSQL cannot query this aborted transaction.
            throw PlanningConflict::forRequestKey($identity->requestKey);
        }
    }

    /** @return array{account_id: string, account_incarnation: string, workspace_scope: string, request_key: string} */
    private function key(TaskOperationIdentity $identity): array
    {
        $workspaceScope = match (true) {
            $identity->kind === TaskOperationKind::CompletionCapture => '',
            $identity->workspaceId === null => 'account',
            default => 'workspace:'.$identity->workspaceId,
        };

        return [
            'account_id' => $identity->scope->accountId,
            'account_incarnation' => $identity->scope->accountIncarnation,
            'workspace_scope' => $workspaceScope,
            'request_key' => $identity->requestKey,
        ];
    }
}
