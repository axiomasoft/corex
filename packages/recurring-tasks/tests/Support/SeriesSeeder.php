<?php

declare(strict_types=1);

namespace CoreX\RecurringTasks\Tests\Support;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\RecurringTasks\Data\SeriesReference;
use CoreX\RecurringTasks\Internal\SeriesDefinitionCodec;
use CoreX\Tasks\Task\Data\TaskDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SeriesSeeder
{
    public static function seed(
        string $seriesKey,
        RecurrenceSet $set,
        TaskDraft $template,
        string $restoreEpoch,
        bool $retired = false,
        ?string $completeThrough = null,
    ): SeriesReference {
        $definition = SeriesDefinitionCodec::encodeSet($set);
        $templateJson = SeriesDefinitionCodec::encodeTemplate($template);
        $actorJson = json_encode(JsonDocuments::decodeTree($template->actor->toArray()), JSON_THROW_ON_ERROR);
        $contextHash = hash('sha256', $template->workspaceId);

        DB::table('corex_task_series')->insert([
            'id' => (string) Str::uuid7(),
            'series_key' => $seriesKey,
            'immutable_revision' => $set->immutableRevision,
            'definition_hash' => $set->definitionHash,
            'definition' => $definition,
            'template' => $templateJson,
            'actor' => $actorJson,
            'context_hash' => $contextHash,
            'restore_epoch' => $restoreEpoch,
        ]);

        if (! DB::table('corex_task_series_state')->where('series_key', $seriesKey)->exists()) {
            DB::table('corex_task_series_state')->insert([
                'series_key' => $seriesKey,
                'active_revision' => $set->immutableRevision,
                'retired' => $retired,
                'complete_through' => $completeThrough,
                'cursor' => null,
                'restore_epoch' => $restoreEpoch,
                'generation' => 0,
            ]);
        }

        return new SeriesReference(
            id: $seriesKey,
            immutableRevision: $set->immutableRevision,
            definitionHash: $set->definitionHash,
        );
    }

    public static function dailySet(string $seriesKey, string $revision, int $count = 5): RecurrenceSet
    {
        return RecurrenceSet::create(
            seriesId: $seriesKey,
            immutableRevision: $revision,
            start: new DateStart(valueType: 'DATE', nominalStart: '2026-09-01', tzid: null),
            rrule: 'FREQ=DAILY;COUNT='.$count,
        );
    }
}
