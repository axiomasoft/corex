<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Internal;

use CoreX\Audit\Enums\ActorType;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\RecurrenceOverride;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Tasks\Task\Data\ActorData;
use CoreX\Tasks\Task\Data\TaskDraft;
use CoreX\Tasks\Task\Data\TaskReference;
use CoreX\Tasks\Task\Enums\TaskPriority;
use DateTimeImmutable;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final class SeriesDefinitionCodec
{
    /** @return array{set: RecurrenceSet, template: TaskDraft, actor: ActorData} */
    public static function decode(string $definitionJson, string $templateJson, string $actorJson): array
    {
        /** @var array<string, mixed> $definition */
        $definition = Value::map(json_decode($definitionJson, true, flags: JSON_THROW_ON_ERROR));
        /** @var array<string, mixed> $template */
        $template = Value::map(json_decode($templateJson, true, flags: JSON_THROW_ON_ERROR));
        /** @var array<string, mixed> $actor */
        $actor = Value::map(json_decode($actorJson, true, flags: JSON_THROW_ON_ERROR));

        $start = Value::map($definition['start']);
        $set = new RecurrenceSet(
            schemaVersion: Value::text($definition['schemaVersion']),
            seriesId: Value::text($definition['seriesId']),
            immutableRevision: Value::text($definition['immutableRevision']),
            start: new DateStart(
                valueType: Value::text($start['valueType']),
                nominalStart: Value::text($start['nominalStart']),
                tzid: isset($start['tzid']) ? Value::text($start['tzid']) : null,
            ),
            rrule: isset($definition['rrule']) ? Value::text($definition['rrule']) : null,
            rdates: Value::strings($definition['rdates'] ?? []),
            exdates: Value::strings($definition['exdates'] ?? []),
            overrides: array_map(
                static fn (array $override): RecurrenceOverride => new RecurrenceOverride(
                    originalRecurrenceId: Value::text($override['originalRecurrenceId']),
                    effectiveStart: isset($override['effectiveStart']) ? Value::text($override['effectiveStart']) : null,
                    cancelled: (bool) ($override['cancelled'] ?? false),
                ),
                Value::maps($definition['overrides'] ?? []),
            ),
            tzdbVersion: Value::text($definition['tzdbVersion']),
            definitionHash: Value::text($definition['definitionHash']),
        );

        $actorData = new ActorData(
            type: ActorType::from(Value::text($actor['type'])),
            id: isset($actor['id']) ? Value::text($actor['id']) : null,
            context: JsonDocuments::capture(is_array($actor['context'] ?? null) ? $actor['context'] : []),
        );
        $draft = new TaskDraft(
            workspaceId: Value::text($template['workspaceId']),
            title: Value::text($template['title']),
            actor: $actorData,
            description: isset($template['description']) ? Value::text($template['description']) : null,
            priority: isset($template['priority']) ? TaskPriority::from(Value::text($template['priority'])) : TaskPriority::Normal,
            projectId: isset($template['projectId']) ? Value::text($template['projectId']) : null,
            sprintId: isset($template['sprintId']) ? Value::text($template['sprintId']) : null,
            stageId: isset($template['stageId']) ? Value::text($template['stageId']) : null,
            parentId: isset($template['parentId']) ? Value::text($template['parentId']) : null,
            authorId: isset($template['authorId']) ? Value::text($template['authorId']) : null,
            assigneeId: isset($template['assigneeId']) ? Value::text($template['assigneeId']) : null,
            startsAt: isset($template['startsAt']) ? new DateTimeImmutable(Value::text($template['startsAt'])) : null,
            dueAt: isset($template['dueAt']) ? new DateTimeImmutable(Value::text($template['dueAt'])) : null,
            estimateMinutes: isset($template['estimateMinutes']) ? Value::integer($template['estimateMinutes']) : null,
            targets: [],
        );

        return ['set' => $set, 'template' => $draft, 'actor' => $actorData];
    }

    public static function encodeSet(RecurrenceSet $set): string
    {
        return json_encode([
            'schemaVersion' => $set->schemaVersion,
            'seriesId' => $set->seriesId,
            'immutableRevision' => $set->immutableRevision,
            'start' => [
                'valueType' => $set->start->valueType,
                'nominalStart' => $set->start->nominalStart,
                'tzid' => $set->start->tzid,
            ],
            'rrule' => $set->rrule,
            'rdates' => $set->rdates,
            'exdates' => $set->exdates,
            'overrides' => array_map(
                static fn (RecurrenceOverride $override): array => [
                    'originalRecurrenceId' => $override->originalRecurrenceId,
                    'effectiveStart' => $override->effectiveStart,
                    'cancelled' => $override->cancelled,
                ],
                $set->overrides,
            ),
            'tzdbVersion' => $set->tzdbVersion,
            'definitionHash' => $set->definitionHash,
        ], JSON_THROW_ON_ERROR);
    }

    public static function encodeTemplate(TaskDraft $draft): string
    {
        return json_encode([
            'workspaceId' => $draft->workspaceId,
            'title' => $draft->title,
            'description' => $draft->description,
            'priority' => $draft->priority->value,
            'projectId' => $draft->projectId,
            'sprintId' => $draft->sprintId,
            'stageId' => $draft->stageId,
            'parentId' => $draft->parentId,
            'authorId' => $draft->authorId,
            'assigneeId' => $draft->assigneeId,
            'startsAt' => $draft->startsAt?->format(DateTimeImmutable::ATOM),
            'dueAt' => $draft->dueAt?->format(DateTimeImmutable::ATOM),
            'estimateMinutes' => $draft->estimateMinutes,
        ], JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $stored */
    public static function taskReferenceFromStored(array $stored): TaskReference
    {
        if (! isset($stored['id'], $stored['type'], $stored['display'])) {
            throw new InvalidArgumentException('Stored task reference is invalid.');
        }

        return new TaskReference(
            id: Value::text($stored['id']),
            type: Value::text($stored['type']),
            display: Value::text($stored['display']),
        );
    }
}
