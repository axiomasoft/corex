<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\DataScope;
use CoreX\Data\WriteToken;
use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Recurrence\Data\ExpansionCursor;
use CoreX\Recurrence\Data\ExpansionLimits;
use CoreX\Recurrence\Data\Occurrence;
use CoreX\Recurrence\Data\RecurrenceWindow;
use CoreX\Tasks\Access\Contracts\TaskAccess;
use CoreX\Tasks\Planning\Contracts\TaskOperationReceipts;
use CoreX\Tasks\Task\Contracts\CreateTaskCommand;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\TasksWorkflow\Contracts\TaskRecurrenceCommand;
use CoreX\TasksWorkflow\Data\MaterializationReceipt;
use CoreX\TasksWorkflow\Data\MaterializationWindow;
use CoreX\TasksWorkflow\Data\SeriesReference;
use CoreX\TasksWorkflow\Exceptions\StaleMaterialization;
use CoreX\TasksWorkflow\Exceptions\UnsupportedMaterialization;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use CoreX\Wakeup\Data\WakeupRequest;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use Override;

final class SqlTaskRecurrenceMaterializer implements TaskRecurrenceCommand
{
    private const string VERSION = MaterializationHasher::HANDLER_VERSION;

    public function __construct(
        private readonly Connection $connection,
        private readonly RecurrenceExpander $expander,
        private readonly CreateTaskCommand $createTask,
        private readonly TaskAccess $taskAccess,
        private readonly Repository $config,
        private readonly Container $container,
        private readonly DataScopeAuthorizer $authorizer,
        private readonly TenantContextRestorer $contexts,
        private readonly TaskOperationReceipts $receipts,
    ) {}

    #[Override]
    public function materialize(
        DataScope $scope,
        SeriesReference $series,
        MaterializationWindow $window,
        WriteToken $token,
    ): MaterializationReceipt {
        (new ScopeConnectionGuard($this->connection))->assertMatches($scope);

        $context = $this->contexts->restore($scope);

        if (! $this->authorizer->authorize(scope: $scope, entity: 'task-series', subjectId: $series->id, fields: [], action: 'materialize')->allowed) {
            throw UnsupportedMaterialization::connection('Materialization is not authorized for the current actor.');
        }

        if ($token->expectedRecordVersion !== $series->immutableRevision) {
            throw StaleMaterialization::revision($series->id, $series->immutableRevision, $token->expectedRecordVersion);
        }

        $payloadHash = MaterializationHasher::payload($scope, $series, $window);

        if ($token->payloadHash !== $payloadHash) {
            throw StaleMaterialization::definition($series->id);
        }

        $store = new MaterializationStore($this->connection, $this->receipts);
        $existing = $store->findOperation($token->operationKey);

        if ($existing !== null) {
            return $store->assertReplayable($existing, $payloadHash);
        }

        $seriesRow = $this->connection
            ->table('corex_task_series')
            ->where('series_key', $series->id)
            ->where('immutable_revision', $series->immutableRevision)
            ->first();

        if ($seriesRow === null || ! hash_equals((string) $seriesRow->definition_hash, $series->definitionHash)) {
            throw StaleMaterialization::definition($series->id);
        }

        $receipt = $this->connection->transaction(function () use ($scope, $context, $series, $window, $token, $payloadHash, $store, $seriesRow): MaterializationReceipt {
            $state = $store->lockSeriesState($series->id);

            if ($state === null) {
                throw StaleMaterialization::definition($series->id);
            }

            if ((bool) $state->retired) {
                return $this->emptyReceipt(retired: true);
            }

            if ((string) $state->active_revision !== $series->immutableRevision) {
                throw StaleMaterialization::revision($series->id, $series->immutableRevision, (string) $state->active_revision);
            }

            if (! hash_equals((string) $state->restore_epoch, $scope->restoreEpoch)) {
                throw StaleMaterialization::restoreEpoch($series->id);
            }

            $decoded = SeriesDefinitionCodec::decode(
                definitionJson: $this->jsonColumn($seriesRow->definition),
                templateJson: $this->jsonColumn($seriesRow->template),
                actorJson: $this->jsonColumn($seriesRow->actor),
            );

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

            $page = $this->expander->expand(
                set: $decoded['set'],
                window: $recurrenceWindow,
                limits: $limits,
                after: null,
            );

            if ($page->status === 'unsupported' || $page->status === 'invalid') {
                throw UnsupportedMaterialization::expansion($page->status);
            }

            $startedAt = microtime(true);
            $taskRefs = [];
            $created = 0;
            $noop = 0;
            $conflict = 0;
            $unknown = false;
            $committedPrefix = $state->complete_through !== null ? (string) $state->complete_through : null;
            $lastCommittedKey = $committedPrefix;

            foreach ($page->occurrences as $occurrence) {
                if ((microtime(true) - $startedAt) >= $window->maxSeconds) {
                    break;
                }

                if (count($taskRefs) >= $window->pageLimit) {
                    break;
                }

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
                    $lastCommittedKey = $occurrence->key;
                    $committedPrefix = $occurrence->effectiveDateOrInstant;
                } else {
                    break;
                }
            }

            if ($unknown) {
                $receipt = $this->buildReceipt(
                    taskRefs: $taskRefs,
                    committedPrefix: $state->complete_through !== null ? (string) $state->complete_through : null,
                    cursor: null,
                    created: $created,
                    noop: $noop,
                    conflict: $conflict,
                    retired: false,
                    unknown: true,
                );
                $store->persistOperation($token->operationKey, $payloadHash, $scope->restoreEpoch, $store->receiptToStored($receipt));

                return $receipt;
            }

            $nextCursor = $page->nextCursor;
            $nextCompleteThrough = $conflict > 0
                ? ($state->complete_through !== null ? (string) $state->complete_through : null)
                : ($lastCommittedKey !== null ? $committedPrefix : ($state->complete_through !== null ? (string) $state->complete_through : null));

            if ($created > 0 || $noop > 0) {
                $store->advanceState(
                    seriesKey: $series->id,
                    generation: (string) $state->generation,
                    completeThrough: $nextCompleteThrough,
                    cursor: $nextCursor === null ? null : [
                        'setHash' => $nextCursor->setHash,
                        'windowHash' => $nextCursor->windowHash,
                        'tzdbVersion' => $nextCursor->tzdbVersion,
                        'libraryVersion' => $nextCursor->libraryVersion,
                        'resumePosition' => $nextCursor->resumePosition,
                    ],
                    restoreEpoch: $scope->restoreEpoch,
                );
            }

            $receipt = $this->buildReceipt(
                taskRefs: $taskRefs,
                committedPrefix: $nextCompleteThrough,
                cursor: $nextCursor,
                created: $created,
                noop: $noop,
                conflict: $conflict,
                retired: false,
                unknown: false,
            );

            $store->persistOperation($token->operationKey, $payloadHash, $scope->restoreEpoch, $store->receiptToStored($receipt));

            return $receipt;
        });

        $this->scheduleWakeup(context: $context, series: $series, window: $window);

        return $receipt;
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
        $existing = $store->lockOccurrence($series->id, $series->immutableRevision, $occurrence->key);

        if ($existing !== null) {
            if ($existing->state === 'unknown') {
                return ['reference' => null, 'created' => false, 'conflict' => false, 'unknown' => true];
            }

            if ($existing->state === 'committed' && $existing->task_ref !== null) {
                /** @var array<string, mixed> $stored */
                $stored = json_decode((string) $existing->task_ref, true, flags: JSON_THROW_ON_ERROR);

                return [
                    'reference' => SeriesDefinitionCodec::taskReferenceFromStored($stored),
                    'created' => false,
                    'conflict' => false,
                    'unknown' => false,
                ];
            }

            if ($existing->state === 'pending' && $existing->task_ref === null) {
                $store->markOccurrenceUnknown((string) $existing->id);

                return ['reference' => null, 'created' => false, 'conflict' => false, 'unknown' => true];
            }

            return ['reference' => null, 'created' => false, 'conflict' => true, 'unknown' => false];
        }

        $operationKey = MaterializationHasher::occurrenceOperationKey($series->id, $series->immutableRevision, $occurrence->key);
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

        $draft = $this->draftForOccurrence($template, $occurrence);
        $reference = $this->createTask->handle($draft);
        $store->commitOccurrence($occurrenceId, [
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
            $aheadEnd = (new DateTimeImmutable($start))->add(new DateInterval('P'.$window->aheadDays.'D'));

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

    private function scheduleWakeup(TenantContext $context, SeriesReference $series, MaterializationWindow $window): void
    {
        if (! $this->config->get('corex-tasks-workflow.wakeup.enabled', false) || ! $this->container->bound(WakeupScheduler::class)) {
            return;
        }

        $kind = (string) $this->config->get('corex-tasks-workflow.wakeup.handler_kind', MaterializationHasher::HANDLER_KIND);
        $fireAt = (new DateTimeImmutable($window->end))->sub(new DateInterval('P1D'));

        $this->container->make(WakeupScheduler::class)->schedule(new WakeupRequest(
            context: $context,
            kind: $kind,
            requestKey: 'materialize:'.$series->id,
            fireAt: $fireAt,
            window: null,
            payload: MaterializationHasher::wakeupPayload(series: $series, window: $window),
        ));
    }
}
