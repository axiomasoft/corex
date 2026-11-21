<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Recurrence\Data\ExpansionCursor;
use CoreX\Recurrence\Data\ExpansionLimits;
use CoreX\Recurrence\Data\Occurrence;
use CoreX\Recurrence\Data\RecurrenceWindow;
use CoreX\Recurrence\Exceptions\StaleCursorException;
use CoreX\RecurringTasks\Contracts\TaskRecurrenceCommand;
use CoreX\RecurringTasks\Data\MaterializationReceipt;
use CoreX\RecurringTasks\Data\MaterializationWindow;
use CoreX\RecurringTasks\Data\SeriesReference;
use CoreX\RecurringTasks\Exceptions\MaterializationConflict;
use CoreX\RecurringTasks\Exceptions\StaleMaterialization;
use CoreX\RecurringTasks\Exceptions\UnsupportedMaterialization;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Planning\Data\TaskOperationIdentity;
use CoreX\Tasks\Planning\Data\TaskOperationKind;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Data\TaskReference;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Override;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final readonly class SqlTaskRecurrenceMaterializer implements TaskRecurrenceCommand
{
    private const string VERSION = MaterializationHasher::HANDLER_VERSION;

    public function __construct(
        private Connection $connection,
        private RecurrenceExpander $expander,
        private CreateTaskCommand $createTask,
        private TaskAccess $taskAccess,
        private bool $wakeupEnabled,
        private string $wakeupHandlerKind,
        private string $wakeupHandlerVersion,
        private Container $container,
        private DataScopeAuthorizer $authorizer,
        private CurrentWorkScope $contexts,
        private TaskOperationReceipts $receipts,
    ) {}

    #[Override]
    public function materialize(
        DataScope $scope,
        SeriesReference $series,
        MaterializationWindow $window,
        WriteToken $token,
    ): MaterializationReceipt {
        $context = $this->contexts->assert($scope, $this->connection);

        if (! $this->authorizer->authorize(scope: $scope, record: new RecordRef('task-series', $series->id), fields: new FieldSelection([]), use: FieldUse::Write)->allowed) {
            throw UnsupportedMaterialization::connection('Materialization is not authorized for the current actor.');
        }

        if ($token->expectedRecordVersion !== $series->immutableRevision) {
            throw StaleMaterialization::revision(seriesKey: $series->id, expected: $series->immutableRevision, actual: $token->expectedRecordVersion);
        }

        $payloadHash = MaterializationHasher::payload(scope: $scope, series: $series, window: $window);

        if ($token->payloadHash !== $payloadHash) {
            throw StaleMaterialization::definition($series->id);
        }

        $scheduler = null;

        if ($this->wakeupEnabled) {
            if (! $this->container->bound(WakeupScheduler::class)) {
                throw UnsupportedMaterialization::connection('Enabled materialization wakeup requires a WakeupScheduler.');
            }
            // Resolve the adapter/handler registry before entering the effect.
            $scheduler = $this->container->make(WakeupScheduler::class);
        }

        $store = new MaterializationStore(connection: $this->connection, receipts: $this->receipts);

        return $this->connection->transaction(function () use ($scope, $context, $series, $window, $token, $payloadHash, $store, $scheduler): MaterializationReceipt {
            $this->contexts->assert($scope, $this->connection);
            $state = $store->lockSeriesState($series->id);
            $seriesRow = $this->connection
                ->table('corex_task_series')
                ->where('series_key', $series->id)
                ->where('immutable_revision', $series->immutableRevision)
                ->first();

            if ($state === null || $seriesRow === null || ! hash_equals(Value::text($seriesRow->definition_hash), $series->definitionHash)) {
                throw StaleMaterialization::definition($series->id);
            }

            $decoded = SeriesDefinitionCodec::decode(
                definitionJson: $this->jsonColumn($seriesRow->definition),
                templateJson: $this->jsonColumn($seriesRow->template),
                actorJson: $this->jsonColumn($seriesRow->actor),
            );
            $workspaceId = $decoded['template']->workspaceId;

            if ($scope->workspaceId !== null && $workspaceId !== $scope->workspaceId) {
                throw new ScopeContextMismatch('The series belongs to another workspace.');
            }

            $identity = new TaskOperationIdentity(
                scope: $scope,
                kind: TaskOperationKind::Materialize,
                requestKey: $token->operationKey,
                subjectRef: 'task-series:'.$series->id,
                workspaceId: $workspaceId,
            );
            $existing = $store->findOperation($identity);

            if ($existing !== null) {
                $receipt = $store->assertReplayable(identity: $identity, existing: $existing, payloadHash: $payloadHash);
                $this->scheduleWakeup(scope: $scope, series: $series, window: $window, payloadHash: $payloadHash, receipt: $receipt, scheduler: $scheduler);

                return $receipt;
            }

            if ((bool) $state->retired) {
                return $this->emptyReceipt(retired: true);
            }

            if (Value::text($state->active_revision) !== $series->immutableRevision) {
                throw StaleMaterialization::revision(seriesKey: $series->id, expected: $series->immutableRevision, actual: Value::text($state->active_revision));
            }

            if (! hash_equals(Value::text($state->restore_epoch), $scope->restoreEpoch)) {
                throw StaleMaterialization::restoreEpoch($series->id);
            }

            ReferenceWrites::enlist($this->connection, $scope, 'axiomasoft/corex-recurring-tasks', 'series', ['series' => $series, 'template' => $payloadHash]);
            $recurrenceWindow = $this->materializationWindow(
                window: $window,
                valueType: $decoded['set']->start->valueType,
            );

            $limits = new ExpansionLimits(
                rangeDays: max(1, $window->aheadDays),
                emitted: min(1000, $window->pageLimit),
                candidateSteps: 100_000,
                wallSeconds: max(1, min(5, $window->maxSeconds)),
                memoryMiB: 128,
            );

            $after = $this->restoreCursor(state: $state, store: $store);

            $startedAt = microtime(true);
            $taskRefs = [];
            $created = 0;
            $noop = 0;
            $conflict = 0;
            $unknown = false;
            $committedPrefix = $state->complete_through !== null ? Value::text($state->complete_through) : null;

            $nextCursor = $after;
            while (count($taskRefs) < $limits->emitted && (microtime(true) - $startedAt) < $window->maxSeconds) {
                // Expand one effect at a time so every cursor belongs to a
                // committed occurrence, even when the task loop stops early.
                $pageLimits = new ExpansionLimits(
                    rangeDays: $limits->rangeDays, emitted: 1, candidateSteps: $limits->candidateSteps,
                    wallSeconds: $limits->wallSeconds, memoryMiB: $limits->memoryMiB,
                );

                try {
                    $page = $this->expander->expand(set: $decoded['set'], window: $recurrenceWindow, limits: $pageLimits, after: $nextCursor);
                } catch (StaleCursorException) {
                    // Revision/window/runtime changes start a new traversal;
                    // occurrence uniqueness preserves already committed effects.
                    $nextCursor = null;
                    $page = $this->expander->expand(set: $decoded['set'], window: $recurrenceWindow, limits: $pageLimits);
                }

                if ($page->status === 'unsupported' || $page->status === 'invalid') {
                    throw UnsupportedMaterialization::expansion($page->status);
                }

                if ($page->occurrences === []) {
                    $nextCursor = $page->nextCursor;

                    break;
                }
                $occurrence = $page->occurrences[0];

                $result = $this->materializeOccurrence(
                    scope: $scope,
                    context: $context,
                    store: $store,
                    series: $series,
                    template: $decoded['template'],
                    occurrence: $occurrence,
                );

                if ($result['unknown']) {
                    $unknown = true;

                    break;
                }

                if ($result['conflict']) {
                    $conflict++;

                    break;
                }

                if ($result['reference'] !== null) {
                    if ($result['created']) {
                        $created++;
                    } else {
                        $noop++;
                    }

                    $taskRefs[] = $result['reference'];
                    $committedPrefix = $occurrence->effectiveDateOrInstant;
                    $nextCursor = $page->nextCursor;
                } else {
                    break;
                }

                if ($nextCursor === null) {
                    break;
                }
            }

            $processed = $created + $noop;
            $nextCompleteThrough = $processed > 0 ? $committedPrefix : ($state->complete_through !== null ? Value::text($state->complete_through) : null);
            $store->advanceState(
                seriesKey: $series->id, generation: Value::text($state->generation), completeThrough: $nextCompleteThrough,
                cursor: $nextCursor === null ? null : [
                    'setHash' => $nextCursor->setHash, 'windowHash' => $nextCursor->windowHash,
                    'tzdbVersion' => $nextCursor->tzdbVersion, 'libraryVersion' => $nextCursor->libraryVersion,
                    'resumePosition' => $nextCursor->resumePosition,
                ],
                restoreEpoch: $scope->restoreEpoch,
            );

            $receipt = $this->buildReceipt(
                taskRefs: $taskRefs,
                committedPrefix: $nextCompleteThrough,
                cursor: $nextCursor,
                created: $created,
                noop: $noop,
                conflict: $conflict,
                retired: false,
                unknown: $unknown,
            );

            $store->persistOperation(identity: $identity, payloadHash: $payloadHash, receipt: $receipt);

            $this->scheduleWakeup(scope: $scope, series: $series, window: $window, payloadHash: $payloadHash, receipt: $receipt, scheduler: $scheduler);

            return $receipt;
        });
    }

    private function restoreCursor(stdClass $state, MaterializationStore $store): ?ExpansionCursor
    {
        if ($state->cursor === null) {
            return null;
        }
        $stored = json_decode($this->jsonColumn($state->cursor), true, flags: JSON_THROW_ON_ERROR);

        return $store->receiptFromStored([
            'cursor' => $stored, 'version' => self::VERSION, 'taskRefs' => [], 'pageDigest' => MaterializationHasher::pageDigest([]),
            'created' => 0, 'noop' => 0, 'conflict' => 0, 'retired' => false, 'unknown' => false,
        ])->cursor;
    }

    /** @return array{reference: ?TaskReference, created: bool, conflict: bool, unknown: bool} */
    private function materializeOccurrence(
        DataScope $scope,
        TenantContext $context,
        MaterializationStore $store,
        SeriesReference $series,
        TaskDraft $template,
        Occurrence $occurrence,
    ): array {
        $existing = $store->lockOccurrence(seriesKey: $series->id, revision: $series->immutableRevision, occurrenceKey: $occurrence->key);

        if ($existing !== null) {
            if ($existing->state === 'unknown') {
                return ['reference' => null, 'created' => false, 'conflict' => false, 'unknown' => true];
            }

            if ($existing->state === 'committed' && $existing->task_ref !== null) {
                /** @var array<string, mixed> $stored */
                $stored = json_decode(Value::text($existing->task_ref), true, flags: JSON_THROW_ON_ERROR);

                return [
                    'reference' => SeriesDefinitionCodec::taskReferenceFromStored($stored),
                    'created' => false,
                    'conflict' => false,
                    'unknown' => false,
                ];
            }

            if ($existing->state === 'pending' && $existing->task_ref === null) {
                $store->markOccurrenceUnknown(Value::text($existing->id));

                return ['reference' => null, 'created' => false, 'conflict' => false, 'unknown' => true];
            }

            return ['reference' => null, 'created' => false, 'conflict' => true, 'unknown' => false];
        }

        $operationKey = MaterializationHasher::occurrenceOperationKey(seriesKey: $series->id, revision: $series->immutableRevision, occurrenceKey: $occurrence->key);
        $occurrenceId = $store->insertPendingOccurrence(
            seriesKey: $series->id,
            revision: $series->immutableRevision,
            occurrenceKey: $occurrence->key,
            originalIdentity: [
                'originalRecurrenceId' => $occurrence->originalRecurrenceId,
                'valueType' => $occurrence->valueType,
                'tzid' => $occurrence->tzid,
                'nominal' => $occurrence->nominal,
            ],
            effectiveAt: $occurrence->effectiveDateOrInstant,
            operationKey: $operationKey,
        );

        $this->taskAccess->authorizeCreate(
            draft: $template,
            context: $context,
        );

        $draft = $this->draftForOccurrence(template: $template, occurrence: $occurrence);
        $reference = $this->createTask->handle($draft);
        $store->commitOccurrence(occurrenceId: $occurrenceId, taskRef: [
            'id' => $reference->id,
            'type' => $reference->type,
            'display' => $reference->display,
        ]);

        return ['reference' => $reference, 'created' => true, 'conflict' => false, 'unknown' => false];
    }

    private function draftForOccurrence(TaskDraft $template, Occurrence $occurrence): TaskDraft
    {
        $startsAt = $template->startsAt;
        $dueAt = $template->dueAt;

        if ($template->startsAt === null && $occurrence->valueType === 'DATE') {
            $startsAt = new DateTimeImmutable($occurrence->effectiveDateOrInstant.' 00:00:00');
        } elseif ($template->startsAt === null) {
            $startsAt = new DateTimeImmutable($occurrence->effectiveDateOrInstant);
        }

        return new TaskDraft(
            workspaceId: $template->workspaceId,
            title: $template->title,
            actor: $template->actor,
            description: $template->description,
            priority: $template->priority,
            projectId: $template->projectId,
            sprintId: $template->sprintId,
            stageId: $template->stageId,
            parentId: $template->parentId,
            authorId: $template->authorId,
            assigneeId: $template->assigneeId,
            startsAt: $startsAt,
            dueAt: $dueAt,
            estimateMinutes: $template->estimateMinutes,
            targets: $template->targets,
        );
    }

    private function materializationWindow(
        MaterializationWindow $window,
        string $valueType,
    ): RecurrenceWindow {
        $start = $window->start;
        $end = $window->end;

        if ($valueType === 'DATE') {
            $endDate = new DateTimeImmutable($end);
            $aheadEnd = new DateTimeImmutable($start)->add(new DateInterval('P'.$window->aheadDays.'D'));

            if ($aheadEnd < $endDate) {
                $end = $aheadEnd->format('Y-m-d');
            }

            return new RecurrenceWindow(valueType: 'DATE', start: $start, end: $end);
        }

        return new RecurrenceWindow(valueType: $valueType, start: $start, end: $end);
    }

    /** @param list<TaskReference> $taskRefs */
    private function buildReceipt(
        array $taskRefs,
        ?string $committedPrefix,
        ?ExpansionCursor $cursor,
        int $created,
        int $noop,
        int $conflict,
        bool $retired,
        bool $unknown,
    ): MaterializationReceipt {
        return new MaterializationReceipt(
            version: self::VERSION,
            taskRefs: $taskRefs,
            pageDigest: MaterializationHasher::pageDigest(array_map(static fn (TaskReference $ref): string => $ref->id, $taskRefs)),
            committedPrefix: $committedPrefix,
            cursor: $cursor,
            created: $created,
            noop: $noop,
            conflict: $conflict,
            retired: $retired,
            unknown: $unknown,
        );
    }

    private function emptyReceipt(bool $retired): MaterializationReceipt
    {
        return $this->buildReceipt(
            taskRefs: [],
            committedPrefix: null,
            cursor: null,
            created: 0,
            noop: 0,
            conflict: 0,
            retired: $retired,
            unknown: false,
        );
    }

    private function jsonColumn(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function scheduleWakeup(DataScope $scope, SeriesReference $series, MaterializationWindow $window, string $payloadHash, MaterializationReceipt $receipt, ?WakeupScheduler $scheduler): void
    {
        if ($scheduler === null || $receipt->retired || $receipt->unknown || $receipt->conflict > 0) {
            return;
        }

        $kind = $this->wakeupHandlerKind;
        $fireAt = new DateTimeImmutable($window->end)->sub(new DateInterval('P1D'));

        $result = $scheduler->schedule(new WakeupRequest(
            scope: $scope,
            kind: $kind,
            handlerVersion: $this->wakeupHandlerVersion,
            requestKey: 'materialize:'.hash('sha256', $payloadHash."\0".json_encode($receipt->cursor?->resumePosition, JSON_THROW_ON_ERROR)),
            fireAt: $fireAt,
            window: null,
            payload: JsonDocuments::capture(MaterializationHasher::wakeupPayload(series: $series, window: $window)),
        ));

        if ($result->status === 'conflict') {
            throw MaterializationConflict::forOperationKey($result->requestKey);
        }

        if (! in_array($result->status, ['created', 'existing'], true)) {
            throw UnsupportedMaterialization::connection('Materialization wakeup was not durably scheduled.');
        }
    }
}
