<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Enums\ActorType;
use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\RecurrenceOverride;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Tasks\Data\ActorData;
use CoreX\Tasks\Data\TaskDraft;
use CoreX\Tasks\Data\TaskReference;
use CoreX\Tasks\Enums\TaskPriority;
use DateTimeImmutable;
use InvalidArgumentException;

final class SeriesDefinitionCodec
{
    /** @return array{set: RecurrenceSet, template: TaskDraft, actor: ActorData} */
    public static function decode(string $definitionJson, string $templateJson, string $actorJson): array
    {
        /** @var array<string, mixed> $definition */
        $definition = json_decode($definitionJson, true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $template */
        $template = json_decode($templateJson, true, flags: JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $actor */
        $actor = json_decode($actorJson, true, flags: JSON_THROW_ON_ERROR);

        $start = $definition['start'];
        $set = new RecurrenceSet(
            schemaVersion: (string) $definition['schemaVersion'],
            seriesId: (string) $definition['seriesId'],
            immutableRevision: (string) $definition['immutableRevision'],
            start: new DateStart(
                valueType: (string) $start['valueType'],
                nominalStart: (string) $start['nominalStart'],
                tzid: isset($start['tzid']) ? (string) $start['tzid'] : null,
            ),
            rrule: isset($definition['rrule']) ? (string) $definition['rrule'] : null,
            rdates: array_map(strval(...), $definition['rdates'] ?? []),
            exdates: array_map(strval(...), $definition['exdates'] ?? []),
            overrides: array_map(
                static fn (array $override): RecurrenceOverride => new RecurrenceOverride(
                    originalRecurrenceId: (string) $override['originalRecurrenceId'],
                    effectiveStart: isset($override['effectiveStart']) ? (string) $override['effectiveStart'] : null,
                    cancelled: (bool) ($override['cancelled'] ?? false),
                ),
                $definition['overrides'] ?? [],
            ),
            tzdbVersion: (string) $definition['tzdbVersion'],
            definitionHash: (string) $definition['definitionHash'],
        );

        $actorData = new ActorData(
            type: ActorType::from((string) $actor['type']),
            id: isset($actor['id']) ? (string) $actor['id'] : null,
            context: is_array($actor['context'] ?? null) ? $actor['context'] : [],
        );
        $draft = new TaskDraft(
            workspaceId: (string) $template['workspaceId'],
            title: (string) $template['title'],
            actor: $actorData,
            description: isset($template['description']) ? (string) $template['description'] : null,
            priority: isset($template['priority']) ? TaskPriority::from((string) $template['priority']) : TaskPriority::Normal,
            projectId: isset($template['projectId']) ? (string) $template['projectId'] : null,
            sprintId: isset($template['sprintId']) ? (string) $template['sprintId'] : null,
            stageId: isset($template['stageId']) ? (string) $template['stageId'] : null,
            parentId: isset($template['parentId']) ? (string) $template['parentId'] : null,
            authorId: isset($template['authorId']) ? (string) $template['authorId'] : null,
            assigneeId: isset($template['assigneeId']) ? (string) $template['assigneeId'] : null,
            startsAt: isset($template['startsAt']) ? new DateTimeImmutable((string) $template['startsAt']) : null,
            dueAt: isset($template['dueAt']) ? new DateTimeImmutable((string) $template['dueAt']) : null,
            estimateMinutes: isset($template['estimateMinutes']) ? (int) $template['estimateMinutes'] : null,
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
            id: (string) $stored['id'],
            type: (string) $stored['type'],
            display: (string) $stored['display'],
        );
    }
}
