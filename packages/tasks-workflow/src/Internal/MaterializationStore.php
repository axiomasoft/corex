<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Recurrence\Data\ExpansionCursor;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Planning\Data\TaskOperationKind;
use CoreX\Tasks\Planning\Data\TaskOperationReceipt;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\TasksWorkflow\Data\MaterializationReceipt;
use CoreX\TasksWorkflow\Exceptions\MaterializationConflict;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use stdClass;

/** @internal Durable materialization receipts and occurrence rows. */
final class MaterializationStore
{
    public function __construct(private readonly Connection $connection, private readonly TaskOperationReceipts $receipts) {}

    public function findOperation(string $operationKey): ?TaskOperationReceipt
    {
        return $this->receipts->find($this->connection, $operationKey);
    }

    public function assertReplayable(TaskOperationReceipt $existing, string $payloadHash): MaterializationReceipt
    {
        if (! hash_equals($existing->payloadHash, $payloadHash)) {
            throw MaterializationConflict::forOperationKey($existing->requestKey);
        }

        $receipt = $this->receiptFromStored(JsonDocuments::map($existing->resultRef));

        return new MaterializationReceipt(
            version: $receipt->version,
            taskRefs: $receipt->taskRefs,
            pageDigest: $receipt->pageDigest,
            committedPrefix: $receipt->committedPrefix,
            cursor: $receipt->cursor,
            created: 0,
            noop: 0,
            conflict: 0,
            retired: $receipt->retired,
            unknown: false,
        );
    }

    /** @param array<string, mixed> $resultRef */
    public function persistOperation(string $operationKey, string $payloadHash, string $restoreEpoch, array $resultRef): void
    {
        try {
            $this->receipts->record(
                connection: $this->connection,
                kind: TaskOperationKind::Materialize,
                requestKey: $operationKey,
                payloadHash: $payloadHash,
                restoreEpoch: $restoreEpoch,
                resultRef: $resultRef,
            );
        } catch (QueryException $exception) {
            $existing = $this->findOperation($operationKey);

            if ($existing === null) {
                throw $exception;
            }

            if (! hash_equals($existing->payloadHash, $payloadHash)) {
                throw MaterializationConflict::forOperationKey($operationKey);
            }
        }
    }

    public function lockSeriesState(string $seriesKey): ?stdClass
    {
        return $this->connection
            ->table('corex_task_series_state')
            ->where('series_key', $seriesKey)
            ->lockForUpdate()
            ->first();
    }

    public function lockOccurrence(string $seriesKey, string $revision, string $occurrenceKey): ?stdClass
    {
        return $this->connection
            ->table('corex_task_occurrences')
            ->where('series_key', $seriesKey)
            ->where('immutable_revision', $revision)
            ->where('occurrence_key', $occurrenceKey)
            ->lockForUpdate()
            ->first();
    }

    /** @param array<string, mixed> $originalIdentity */
    public function insertPendingOccurrence(
        string $seriesKey,
        string $revision,
        string $occurrenceKey,
        array $originalIdentity,
        string $effectiveAt,
        string $operationKey,
    ): string {
        $id = (string) Str::uuid7();
        $this->connection->table('corex_task_occurrences')->insert([
            'id' => $id,
            'series_key' => $seriesKey,
            'immutable_revision' => $revision,
            'occurrence_key' => $occurrenceKey,
            'original_identity' => json_encode($originalIdentity, JSON_THROW_ON_ERROR),
            'effective_at' => $effectiveAt,
            'task_ref' => null,
            'operation_key' => $operationKey,
            'state' => 'pending',
        ]);

        return $id;
    }

    /** @param array<string, mixed> $taskRef */
    public function commitOccurrence(string $occurrenceId, array $taskRef): void
    {
        $this->connection->table('corex_task_occurrences')->where('id', $occurrenceId)->update([
            'task_ref' => json_encode($taskRef, JSON_THROW_ON_ERROR),
            'state' => 'committed',
        ]);
    }

    public function markOccurrenceUnknown(string $occurrenceId): void
    {
        $this->connection->table('corex_task_occurrences')->where('id', $occurrenceId)->update([
            'state' => 'unknown',
        ]);
    }

    /** @param array<string, mixed>|null $cursor */
    public function advanceState(
        string $seriesKey,
        string $generation,
        ?string $completeThrough,
        ?array $cursor,
        string $restoreEpoch,
    ): void {
        $updated = $this->connection->table('corex_task_series_state')
            ->where('series_key', $seriesKey)
            ->where('generation', $generation)
            ->update([
                'complete_through' => $completeThrough,
                'cursor' => $cursor === null ? null : json_encode($cursor, JSON_THROW_ON_ERROR),
                'restore_epoch' => $restoreEpoch,
                'generation' => \bcadd($generation, '1', 0),
            ]);

        if ($updated !== 1) {
            throw MaterializationConflict::forGeneration($seriesKey);
        }
    }

    /** @param array<string, mixed> $stored */
    public function receiptFromStored(array $stored): MaterializationReceipt
    {
        $taskRefs = array_map(
            SeriesDefinitionCodec::taskReferenceFromStored(...),
            $stored['taskRefs'] ?? [],
        );

        $cursor = null;

        if (isset($stored['cursor']) && is_array($stored['cursor'])) {
            $resume = $stored['cursor']['resumePosition'];

            if (! is_array($resume)) {
                throw new InvalidArgumentException('Stored cursor position must be a map.');
            }
            foreach ($resume as $key => $value) {
                if (! in_array($key, ['scanned', 'emitted'], true) || ! is_int($value)) {
                    throw new InvalidArgumentException('Stored cursor position must contain integer counters.');
                }
            }
            /** @var array{scanned?: int, emitted?: int} $resume */
            $cursor = new ExpansionCursor(
                setHash: (string) $stored['cursor']['setHash'],
                windowHash: (string) $stored['cursor']['windowHash'],
                tzdbVersion: (string) $stored['cursor']['tzdbVersion'],
                libraryVersion: (string) $stored['cursor']['libraryVersion'],
                resumePosition: $resume,
            );
        }

        return new MaterializationReceipt(
            version: (string) $stored['version'],
            taskRefs: $taskRefs,
            pageDigest: (string) $stored['pageDigest'],
            committedPrefix: isset($stored['committedPrefix']) ? (string) $stored['committedPrefix'] : null,
            cursor: $cursor,
            created: (int) $stored['created'],
            noop: (int) $stored['noop'],
            conflict: (int) $stored['conflict'],
            retired: (bool) $stored['retired'],
            unknown: (bool) $stored['unknown'],
        );
    }

    /** @return array<string, mixed> */
    public function receiptToStored(MaterializationReceipt $receipt): array
    {
        return [
            'version' => $receipt->version,
            'taskRefs' => array_map(
                static fn (TaskReference $reference): array => [
                    'id' => $reference->id,
                    'type' => $reference->type,
                    'display' => $reference->display,
                ],
                $receipt->taskRefs,
            ),
            'pageDigest' => $receipt->pageDigest,
            'committedPrefix' => $receipt->committedPrefix,
            'cursor' => $receipt->cursor === null ? null : [
                'setHash' => $receipt->cursor->setHash,
                'windowHash' => $receipt->cursor->windowHash,
                'tzdbVersion' => $receipt->cursor->tzdbVersion,
                'libraryVersion' => $receipt->cursor->libraryVersion,
                'resumePosition' => $receipt->cursor->resumePosition,
            ],
            'created' => $receipt->created,
            'noop' => $receipt->noop,
            'conflict' => $receipt->conflict,
            'retired' => $receipt->retired,
            'unknown' => $receipt->unknown,
        ];
    }
}
