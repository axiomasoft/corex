<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Types\Contracts\FieldConversions;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use stdClass;

#[Internal]
final readonly class ShadowBackfill
{
    private const int BATCH = 50;

    public function __construct(private FieldConversions $conversions, private ClockInterface $clock) {}

    public function begin(Connection $db, DataScope $scope, string $entity, stdClass $claim): void
    {
        // Expand holds the head exclusively. Later inserts, including keys behind this bound, dual-write from birth.
        $upper = SchemaRows::rows($db, SchemaRows::DOCUMENTS, $scope, $entity)->max('record_id');
        foreach (['scan', 'verify'] as $partition) {
            $db->table('sys_attribute_backfills')->insert(['operation_id' => $claim->id, 'partition_id' => $partition,
                'kind' => $partition, 'upper_record_id' => $upper]);
        }
    }

    /** @param array<string,mixed> $plan */
    public function batch(Connection $db, DataScope $scope, stdClass $claim, array $plan): string
    {
        $ledger = $db->table('sys_attribute_backfills')->where('operation_id', $claim->id);
        $scan = (clone $ledger)->where('partition_id', 'scan')->sole();

        if (! $scan->exhausted) {
            $this->page($db, $scope, $claim, $plan, $scan);

            return 'backfill';
        }

        // Apply the revision filter in PostgreSQL so an arbitrarily large set of unchanged errors cannot starve corrected rows.
        $documents = SchemaRows::rows($db, SchemaRows::DOCUMENTS, $scope, SchemaValue::text($plan['entity']))->select('record_id', 'document_revision');
        $markers = $db->table('sys_attribute_backfills as f')->leftJoinSub($documents, 'd', 'd.record_id', '=', 'f.record_id')
            ->where('f.operation_id', $claim->id)->whereIn('f.kind', ['retry', 'error'])
            ->where(static fn (Builder $q) => $q->where('f.kind', 'retry')->orWhereRaw('d.document_revision IS DISTINCT FROM f.seen_revision'))
            ->distinct()->orderBy('f.record_id')->limit(self::BATCH)->get(['f.record_id']);
        $rows = $this->documents($db, $scope, $plan);
        foreach ($markers as $marker) {
            $row = (clone $rows)->where('record_id', $marker->record_id)->first();

            if ($row === null) {
                (clone $ledger)->where('record_id', $marker->record_id)->delete();
            } else {
                $this->copy($db, $scope, $claim, $plan, $row);
            }

            if ($this->yieldClaim($claim)) {
                return 'backfill';
            }
        }

        if ($markers->count() === self::BATCH || (clone $ledger)->where('kind', 'retry')->exists()) {
            return 'backfill';
        }

        return (clone $ledger)->where('kind', 'error')->exists() ? 'blocked' : 'verify';
    }

    /** @param array<string,mixed> $plan */
    public function verify(Connection $db, DataScope $scope, stdClass $claim, array $plan): bool
    {
        $ledger = $db->table('sys_attribute_backfills')->where('operation_id', $claim->id);

        if (! (clone $ledger)->where('partition_id', 'scan')->where('exhausted', true)->exists()
            || (clone $ledger)->whereIn('kind', ['error', 'retry'])->exists()) {
            throw new SchemaConflict('historical-conversions-pending');
        }
        $verified = (clone $ledger)->where('partition_id', 'verify')->sole();

        if ($verified->exhausted) {
            return true;
        }

        if ($claim->phase === 'switch') {
            throw new SchemaConflict('historical-verification-incomplete');
        }

        return $this->page($db, $scope, $claim, $plan, $verified);
    }

    /** @param array<string,mixed> $plan */
    private function page(Connection $db, DataScope $scope, stdClass $claim, array $plan, stdClass $checkpoint): bool
    {
        $rows = $this->documents($db, $scope, $plan);
        $verify = $checkpoint->partition_id === 'verify';

        if ($verify) {
            $target = SchemaValue::text(SchemaValue::map($plan['to'])['slot']);
            $rows->selectRaw('(slots -> CAST(? AS text))::text AS shadow_value', [$target]);
        }
        $this->range($rows, $checkpoint);
        $selected = $rows->get();
        $cursor = $checkpoint->cursor_record_id;
        $processed = 0;
        foreach ($selected as $row) {
            if ($verify) {
                $this->verifyRow($db, $plan, $row);
            } else {
                $this->copy($db, $scope, $claim, $plan, $row);
            }
            $cursor = $row->record_id;
            $processed++;

            if ($this->yieldClaim($claim)) {
                break;
            }
        }
        $done = $processed === $selected->count() && ($processed < self::BATCH || $cursor === $checkpoint->upper_record_id);
        $this->advanceCursor($db, $claim, $checkpoint, $cursor, $done);

        return $done;
    }

    private function yieldClaim(stdClass $claim): bool
    {
        // Reserve half the claim for the next conversion's runtime variance and the journal CAS.
        return $this->clock->now() >= (new DateTimeImmutable(SchemaValue::text($claim->lease_until)))->modify(sprintf('-%d seconds', intdiv(SchemaJournal::LEASE_SECONDS, 2)));
    }

    private function range(Builder $query, stdClass $checkpoint): void
    {
        if ($checkpoint->upper_record_id === null) {
            $query->whereRaw('FALSE');
        } else {
            $query->where('record_id', '<=', $checkpoint->upper_record_id);
        }

        if ($checkpoint->cursor_record_id !== null) {
            $query->where('record_id', '>', $checkpoint->cursor_record_id);
        }
        $query->orderBy('record_id')->limit(self::BATCH);
    }

    private function advanceCursor(Connection $db, stdClass $claim, stdClass $checkpoint, mixed $cursor, bool $done): void
    {
        SchemaRows::one($db->table('sys_attribute_backfills')->where('operation_id', $claim->id)->where('partition_id', $checkpoint->partition_id)
            ->update(['cursor_record_id' => $cursor, 'exhausted' => $done]));
    }

    /** @param array<string,mixed> $plan */
    private function verifyRow(Connection $db, array $plan, stdClass $row): void
    {
        $expected = $this->convert($plan, $row);

        if (($expected === null) !== ($row->shadow_value === null)) {
            throw new SchemaConflict('shadow-verification-failed');
        }

        if ($expected !== null) {
            $match = $db->selectOne('SELECT CAST(? AS jsonb) = CAST(? AS jsonb) AS matches', [$expected->canonicalJson, $row->shadow_value], false);

            if (! $match instanceof stdClass || $match->matches !== true) {
                throw new SchemaConflict('shadow-verification-failed');
            }
        }
    }

    public function discard(Connection $db, DataScope $scope, string $entity, stdClass $claim, string $slot): bool
    {
        $rows = SchemaRows::rows($db, SchemaRows::DOCUMENTS, $scope, $entity);
        $ledger = $db->table('sys_attribute_backfills')->where('operation_id', $claim->id)->where('partition_id', 'cleanup');
        $checkpoint = $ledger->first();

        if ($checkpoint === null) {
            $db->table('sys_attribute_backfills')->insert(['operation_id' => $claim->id, 'partition_id' => 'cleanup',
                'kind' => 'cleanup', 'upper_record_id' => (clone $rows)->max('record_id')]);
            $checkpoint = $ledger->sole();
        }

        if ($checkpoint->exhausted) {
            return true;
        }
        $this->range($rows, $checkpoint);
        $keys = $rows->pluck('record_id');
        $cursor = $checkpoint->cursor_record_id;

        if ($keys->isNotEmpty()) {
            $cursor = $keys->last();
            // The exclusive head drains old writes. No writer may put this retired slot behind the cursor.
            $db->update('UPDATE sys_attribute_documents SET slots=slots - CAST(? AS text) WHERE account_id=? AND account_incarnation=? AND workspace_scope=? AND entity=? AND record_id BETWEEN ? AND ? AND jsonb_exists(slots, ?)',
                [$slot, ...array_values(SchemaRows::logical($scope)), $entity, $keys->first(), $keys->last(), $slot]);
        }
        $done = $keys->count() < self::BATCH || $cursor === $checkpoint->upper_record_id;
        $this->advanceCursor($db, $claim, $checkpoint, $cursor, $done);

        return $done;
    }

    /** @param array<string,mixed> $plan */
    private function documents(Connection $db, DataScope $scope, array $plan): Builder
    {
        return SchemaRows::rows($db, SchemaRows::DOCUMENTS, $scope, SchemaValue::text($plan['entity']))->select(['record_id', 'document_revision'])
            ->selectRaw('(slots -> CAST(? AS text))::text AS source_value', [SchemaValue::text(SchemaValue::map($plan['from'])['slot'])]);
    }

    /** @param array<string,mixed> $plan */
    private function convert(array $plan, stdClass $row): ?JsonDocument
    {
        if ($row->source_value === null) {
            return null;
        }
        $from = SchemaImage::hydrate(SchemaValue::map(SchemaValue::map($plan['from'])['definition']));
        $to = SchemaImage::hydrate(SchemaValue::map(SchemaValue::map($plan['to'])['definition']));

        return $this->conversions->convert(new JsonDocument(SchemaValue::text($row->source_value)), $from, $to, SchemaValue::nullableText($plan['converter_id']),
            $plan['converter_version'] === null ? null : SchemaValue::integer($plan['converter_version']));
    }

    /** @param array<string,mixed> $plan */
    private function copy(Connection $db, DataScope $scope, stdClass $claim, array $plan, stdClass $row): void
    {
        $key = [...SchemaRows::logical($scope), 'entity' => SchemaValue::text($plan['entity']), 'record_id' => SchemaValue::text($row->record_id), 'document_revision' => SchemaValue::text($row->document_revision)];
        $slot = SchemaValue::text(SchemaValue::map($plan['to'])['slot']);

        try {
            $value = $this->convert($plan, $row);
        } catch (InvalidArgumentException|FieldFailure $failure) {
            // Conversion can race a correction. Only a still-current failed source blocks verification.
            $kind = $db->table('sys_attribute_documents')->where($key)->exists() ? 'error' : 'retry';
            $this->marker($db, $claim, $row, $kind);

            return;
        }
        $where = ' WHERE account_id=? AND account_incarnation=? AND workspace_scope=? AND entity=? AND record_id=? AND document_revision=?';
        $changed = $value === null
            ? $db->update('UPDATE sys_attribute_documents SET slots = slots - CAST(? AS text)'.$where, [$slot, ...array_values($key)])
            : $db->update('UPDATE sys_attribute_documents SET slots = jsonb_set(slots, ARRAY[CAST(? AS text)], CAST(? AS jsonb), true)'.$where, [$slot, $value->canonicalJson, ...array_values($key)]);

        if ($changed === 0) {
            $this->marker($db, $claim, $row, 'retry');

            return;
        }
        SchemaRows::one($changed);
        $db->table('sys_attribute_backfills')->where('operation_id', $claim->id)->where('record_id', $row->record_id)->whereIn('kind', ['retry', 'error'])->delete();
    }

    private function marker(Connection $db, stdClass $claim, stdClass $row, string $kind): void
    {
        $part = $kind.'/'.base64_encode(SchemaValue::text($row->record_id)).($kind === 'error' ? '/'.SchemaValue::text($row->document_revision) : '');
        // A previous failed source revision is no longer actionable after this new observation.
        $db->table('sys_attribute_backfills')->where('operation_id', $claim->id)->where('record_id', $row->record_id)->where('partition_id', '<>', $part)->delete();
        $db->table('sys_attribute_backfills')->updateOrInsert(['operation_id' => $claim->id, 'partition_id' => $part],
            ['kind' => $kind, 'record_id' => $row->record_id, 'seen_revision' => $row->document_revision, 'error_code' => $kind === 'error' ? 'conversion-failed' : null]);
    }
}
