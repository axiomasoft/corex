<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\ProviderReceiptProjector;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Data\Data\EntityHandle;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Enums\IndexOutcome;
use CoreX\Search\Internal\Value;
use DateTimeImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use LogicException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use stdClass;

#[Internal]
final readonly class IndexReceiptProjector implements ProviderReceiptProjector
{
    public function __construct(private DatabaseManager $database, private ProviderEffectRecovery $recovery, private LaneStore $store,
        private Container $container, private ClockInterface $clock) {}

    public function owner(): string
    {
        return Submission::OWNER;
    }

    public function lookup(ProviderEffectStatus $status): ?ProviderObservation
    {
        $db = $this->database->connection();
        [, $work] = $this->read(db: $db, status: $status);

        if (in_array($work->row->state, ['planned', 'cancelled'], true)) {
            $absent = $db->transaction(function () use ($db, $status): bool {
                [, $locked] = $this->read(db: $db, status: $status, lock: true);

                return GenerationWorker::query(db: $db, row: $locked->row)->whereIn('state', ['planned', 'cancelled'])
                    ->update(['state' => 'not-sent', 'provider_ticket' => $status->ticket->id]) === 1;
            });

            if (! $absent) {
                return null;
            }

            return new ProviderObservation(kind: ProviderObservationKind::Absent, reference: $work->batch->submissionId, observer: $this->owner());
        }

        if ($work->row->state === 'not-sent') {
            return new ProviderObservation(kind: ProviderObservationKind::Absent, reference: $work->batch->submissionId, observer: $this->owner());
        }

        if ($work->row->state === 'sending' && $work->row->lease_until !== null
            && new DateTimeImmutable(Value::text($work->row->lease_until)) > $this->clock->now()) {
            return null;
        }
        $receipt = $work->row->task_ref ?? $status->firstProviderRef;
        $outcome = $this->container->make(IndexDriver::class)->lookup($work->batch, $receipt === null ? null : new IndexReceipt(Value::text($receipt)));
        $kind = match ($outcome) {
            IndexOutcome::Confirmed => ProviderObservationKind::Confirmed,
            IndexOutcome::Failed => ProviderObservationKind::Permanent,
            IndexOutcome::Unknown => ProviderObservationKind::Unknown,
            IndexOutcome::Pending => null,
        };

        if ($outcome === IndexOutcome::Unknown) {
            // Core deliberately leaves ambiguous effects outstanding. Record the external observation
            // before isolating the immutable UID in the owner's transaction.
            $this->container->make(ProviderEffectLedger::class)->observe($status->ticket, new ProviderObservation(
                kind: ProviderObservationKind::Unknown, reference: $receipt === null ? $work->batch->submissionId : Value::text($receipt), observer: $this->owner()));
            $db->transaction(function () use ($db, $status): void {
                [$lane, $work] = $this->read(db: $db, status: $status, lock: true);
                GenerationWorker::finish(db: $db, lane: $lane, work: $work, outcome: IndexOutcome::Unknown);
            });
        }

        return $kind === null ? null : new ProviderObservation(kind: $kind, reference: $receipt === null ? $work->batch->submissionId : Value::text($receipt), observer: $this->owner());
    }

    public function project(ProviderEffectStatus $status): ?string
    {
        $db = $this->database->connection();

        return $db->transaction(function () use ($db, $status): ?string {
            [$lane, $work] = $this->read(db: $db, status: $status, lock: true);

            if ($status->state === 'conflicting') {
                $generation = LaneStore::generation(db: $db, lane: $lane, number: $work->batch->target->generation)->where('uid', $work->batch->target->uid);
                LaneStore::one($generation->update(['state' => 'quarantined']));

                if ($lane->rebuild_id !== null && $db->table(LaneStore::RUNS)->where('rebuild_id', $lane->rebuild_id)->where('to_generation', $work->batch->target->generation)->exists()) {
                    RebuildJournal::release(db: $db, lane: $lane, id: Value::text($lane->rebuild_id), state: 'failed');
                }
                GenerationWorker::query(db: $db, row: $work->row)->whereIn('state', ['sending', 'accepted'])->update(['state' => 'unknown']);

                return null;
            }
            $result = match ($status->observation?->kind) {
                ProviderObservationKind::Confirmed => IndexOutcome::Confirmed,
                ProviderObservationKind::Absent, ProviderObservationKind::Permanent => IndexOutcome::Failed,
                default => null,
            };

            if ($result === null) {
                return null;
            }
            GenerationWorker::finish(db: $db, lane: $lane, work: $work, outcome: $result);

            return $work->batch->submissionId;
        });
    }

    /** @return array{stdClass,Submission} */
    private function read(ConnectionInterface $db, ProviderEffectStatus $status, bool $lock = false): array
    {
        $this->recovery->assertReceipt(status: $status, connection: $db);
        $scope = $status->ticket->scope;
        $query = $db->table(LaneStore::LANES)->useWritePdo()->where($this->store->identity($scope))->whereIn('lane_key',
            $db->table(LaneStore::SUBMISSIONS)->select('lane_key')->where('submission_id', $status->ownerRef));
        $lane = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
        $row = $db->table(LaneStore::SUBMISSIONS)->useWritePdo()->where('lane_key', $lane->lane_key)->where('submission_id', $status->ownerRef)->firstOrFail();
        $generation = LaneStore::generation(db: $db, lane: $lane, number: Value::integer($row->generation))->useWritePdo()->firstOrFail();
        $work = Submission::restore(target: $this->store->target(scope: $scope, entity: new EntityHandle(Value::text($lane->entity)), generation: $generation), row: $row);

        if ($work->effect()->identity() !== $status->effect->identity() || $status->digest !== $row->batch_digest || $status->claimToken !== Value::text($row->claim_epoch)
            || ($row->provider_ticket !== $status->ticket->id && ! in_array($row->state, ['planned', 'cancelled'], true))) {
            throw new LogicException('Index control observation belongs to another claim.');
        }

        return [$lane, $work];
    }
}
