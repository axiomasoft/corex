<?php

declare(strict_types=1);

namespace CoreX\Search\Internal\Index;

use Closure;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Contracts\IndexWorker;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexDocument;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Index\Enums\IndexOutcome;
use CoreX\Search\Internal\Value;
use CoreX\Search\Projection\Data\ProjectedValues;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use LogicException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use stdClass;
use Throwable;

#[Internal]
final readonly class GenerationWorker implements IndexWorker
{
    /**
     * @param  Closure():IndexProjection  $projection
     * @param  Closure():IndexDriver  $driver
     */
    public function __construct(private LaneStore $store, private Closure $projection, private Closure $driver,
        private ProviderEffectLedger $effects, private ScopeAdmission $admission, private ClockInterface $clock) {}

    public function advance(DataScope $scope, EntityHandle $entity, int $generation): bool
    {
        $db = $this->store->db($scope, true);

        if ($db->transactionLevel() !== 0) {
            throw new LogicException('Index submission requires independent durable commits.');
        }

        $work = $db->transaction(function () use ($db, $scope, $entity, $generation): Submission|bool {
            $lane = $this->store->lane($db, $scope, $entity);
            $gen = LaneStore::generation($db, $lane, $generation)->lockForUpdate()->firstOrFail();
            $settlement = $db->table(LaneStore::SUBMISSIONS)->where('lane_key', $lane->lane_key)->where('generation', $generation)
                ->where('control_recorded', false)->whereNotNull('provider_ticket')->whereIn('state', ['confirmed', 'failed', 'not-sent', 'unknown'])
                ->orderBy('claim_epoch')->first();

            if ($settlement !== null) {
                return Submission::restore($this->store->target($scope, $entity, $gen), $settlement);
            }
            $row = $db->table(LaneStore::SUBMISSIONS)->where('lane_key', $lane->lane_key)->where('generation', $generation)
                ->whereIn('state', ['planned', 'sending', 'accepted', 'unknown'])->lockForUpdate()->first();

            if ($row === null) {
                ReferenceWrites::enlist($db, $scope, 'axiomasoft/corex-search', 'generation', ['entity' => $entity->value, 'generation' => $generation]);

                if ($gen->state === 'retained' && $gen->retain_until !== null && new DateTimeImmutable(Value::text($gen->retain_until)) <= $this->clock->now()) {
                    LaneStore::one(LaneStore::generation($db, $lane, $generation)->where('state', 'retained')->where('claim_epoch', $gen->claim_epoch)->update(['state' => 'retired']));

                    return true;
                }

                return $this->plan($db, $scope, $entity, $lane, $gen);
            }

            if ($row->state === 'unknown' || ($row->state === 'sending' && $row->lease_until !== null
                && new DateTimeImmutable(Value::text($row->lease_until)) > $this->clock->now())) {
                return false;
            }

            if ($row->state === 'planned' && ! $this->canSend($lane, $gen, IndexAction::from(Value::text($row->action)))) {
                LaneStore::one(self::query($db, $row)->where('state', 'planned')->update(['state' => 'cancelled']));

                return true;
            }

            return Submission::restore($this->store->target($scope, $entity, $gen), $row);
        });

        if (! $work instanceof Submission) {
            return $work;
        }

        if ($work->row->state === 'planned') {
            return $this->send($work);
        }
        $status = $this->effects->inspect($work->effect());

        if ($status === null || $status->ticket->id !== $work->row->provider_ticket || $status->digest !== $work->row->batch_digest) {
            throw new LogicException('Index control receipt is missing or inconsistent.');
        }

        if (in_array($work->row->state, ['confirmed', 'failed', 'not-sent', 'unknown'], true)) {
            if ($work->row->state === 'not-sent' && $status->observation === null) {
                $this->effects->observe($status->ticket, new ProviderObservation(ProviderObservationKind::Absent, $work->batch->submissionId, Submission::OWNER));
            }

            if ($status->state === 'outstanding') {
                $this->admission->settle($status->ticket, new AttemptEvidence($work->row->state === 'unknown' ? 'unknown' : 'reconciled', $work->batch->submissionId));
            }
            $this->recordSettlement($work);

            return true;
        }
        $receipt = $work->row->task_ref ?? $status->firstProviderRef;
        $outcome = $status->state === 'conflicting' ? IndexOutcome::Unknown : ($this->driver)()->lookup($work->batch, $receipt === null ? null : new IndexReceipt(Value::text($receipt)));

        if ($outcome === IndexOutcome::Pending) {
            return false;
        }
        $kind = match ($outcome) {
            IndexOutcome::Confirmed => ProviderObservationKind::Confirmed,
            IndexOutcome::Failed => ProviderObservationKind::Permanent,
            IndexOutcome::Unknown => ProviderObservationKind::Unknown,
        };
        $this->effects->observe($status->ticket, new ProviderObservation($kind, $receipt === null ? $work->batch->submissionId : Value::text($receipt), Submission::OWNER));
        $latest = $this->effects->inspect($work->effect());

        if ($latest?->state === 'conflicting') {
            $outcome = IndexOutcome::Unknown;
        }
        $db->transaction(function () use ($db, $scope, $entity, $work, $outcome): void {
            $lane = $this->store->lane($db, $scope, $entity);
            self::finish($db, $lane, $work, $outcome);
        });

        if ($latest?->state !== 'conflicting') {
            $this->admission->settle($status->ticket, new AttemptEvidence($outcome === IndexOutcome::Unknown ? 'unknown' : 'reconciled', $work->batch->submissionId));
        }

        $this->recordSettlement($work);

        return true;
    }

    private function recordSettlement(Submission $work): void
    {
        $t = $work->batch->target;
        $db = $this->store->db($t->scope, true);
        $db->transaction(function () use ($db, $t, $work): void {
            $this->store->lane($db, $t->scope, $t->entity);
            self::query($db, $work->row)->whereIn('state', ['confirmed', 'failed', 'not-sent', 'unknown'])->update(['control_recorded' => true]);
        });
    }

    private function send(Submission $work): bool
    {
        $target = $work->batch->target;
        $db = $this->store->db($target->scope, true);
        $ticket = $db->transaction(function () use ($db, $target, $work): AdmissionTicket|bool {
            $lane = $this->store->lane($db, $target->scope, $target->entity);
            $gen = LaneStore::generation($db, $lane, $target->generation)->lockForUpdate()->firstOrFail();
            $row = self::query($db, $work->row)->where('state', 'planned')->lockForUpdate()->first();

            if ($row === null || ! $this->canSend($lane, $gen, $work->batch->action)) {
                return false;
            }
            $until = $this->clock->now()->modify('+30 seconds');
            $prior = $this->effects->inspect($work->effect());

            if (! ($this->projection)()->current($work->batch)) {
                LaneStore::one(self::query($db, $row)->where('state', 'planned')->update([
                    'state' => $prior === null ? 'cancelled' : 'not-sent', 'provider_ticket' => $prior?->ticket->id]));

                return true;
            }

            // A durable control reservation may precede a rolled-back local sending transition.
            if ($prior !== null) {
                if ($prior->ownerRef !== $work->batch->submissionId || $prior->claimToken !== Value::text($row->claim_epoch)
                    || $prior->digest !== $row->batch_digest || $prior->observation !== null || $prior->state !== 'outstanding') {
                    throw new LogicException('Index control reservation is no longer usable.');
                }
                $ticket = $prior->ticket;
            } else {
                $ticket = ReferenceWrites::reserveEffect($target->scope, new ProviderAttempt($work->effect(), $work->batch->submissionId,
                    Value::text($row->claim_epoch), 1, $until, Value::text($row->batch_digest), true), 'axiomasoft/corex-search', $this->effects);
            }
            LaneStore::one(self::query($db, $row)->where('state', 'planned')->update(['state' => 'sending', 'provider_ticket' => $ticket->id,
                'lease_until' => $until->format('Y-m-d H:i:s.uP')]));

            return $ticket;
        });

        if (is_bool($ticket)) {
            return $ticket;
        }

        if ($ticket->key->deadline === null || $ticket->key->deadline <= $this->clock->now()) {
            $db->transaction(function () use ($db, $target, $work, $ticket): void {
                $this->store->lane($db, $target->scope, $target->entity);
                LaneStore::one(self::query($db, $work->row)->where('state', 'sending')->where('provider_ticket', $ticket->id)
                    ->update(['state' => 'not-sent', 'lease_until' => null]));
            });
            $this->effects->observe($ticket, new ProviderObservation(ProviderObservationKind::Absent, $work->batch->submissionId, Submission::OWNER));
            $this->admission->settle($ticket, new AttemptEvidence('reconciled', $work->batch->submissionId));

            return true;
        }

        try {
            $receipt = ($this->driver)()->submit($work->batch);
        } catch (Throwable) {
            // The provider may have accepted the request. Only exact lookup can authorize settlement.
            $db->transaction(function () use ($db, $target, $work, $ticket): void {
                $this->store->lane($db, $target->scope, $target->entity);
                self::query($db, $work->row)->where('state', 'sending')->where('provider_ticket', $ticket->id)->update(['lease_until' => null]);
            });

            return true;
        }
        $this->effects->observe($ticket, new ProviderObservation(ProviderObservationKind::Accepted, $receipt->taskId, Submission::OWNER));
        $db->transaction(function () use ($db, $target, $work, $ticket, $receipt): void {
            $this->store->lane($db, $target->scope, $target->entity);
            self::query($db, $work->row)->where('state', 'sending')->where('provider_ticket', $ticket->id)
                ->update(['state' => 'accepted', 'task_ref' => $receipt->taskId, 'lease_until' => null]);
        });

        return true;
    }

    private function canSend(stdClass $lane, stdClass $gen, IndexAction $action): bool
    {
        return match ($gen->state) {
            'allocated', 'scanning', 'catching-up' => $lane->rebuild_id !== null,
            'active' => $lane->writer_generation === $gen->generation && $lane->freeze_seq === null,
            'retired' => $action === IndexAction::Drop,
            default => false,
        };
    }

    private function plan(ConnectionInterface $db, DataScope $scope, EntityHandle $entity, stdClass $lane, stdClass $gen): bool
    {
        $action = match ($gen->state) {
            'allocated' => IndexAction::Create,
            'retired' => IndexAction::Drop,
            default => IndexAction::Upsert,
        };

        if (! $this->canSend($lane, $gen, $action)) {
            return false;
        }
        $seq = null;
        $record = null;
        $next = null;

        if ($action === IndexAction::Upsert) {
            if ($gen->state === 'scanning') {
                $after = $gen->scan_cursor === null ? null : new RecordRef($entity->value, Value::text($gen->scan_cursor));
                $page = ($this->projection)()->source->page($scope, $entity, $after, 1);
                $records = $page->records->records;

                if (count($records) > 1 || ($page->next !== null && ($records === [] || $page->next->id !== $records[0]->id || $page->next->entity !== $records[0]->entity))) {
                    throw new LogicException('Index source returned an invalid keyset checkpoint.');
                }
                $record = $records[0] ?? null;

                if ($record === null) {
                    self::scanDone($db, $lane, $gen);

                    return true;
                }

                if ($record->entity !== $entity->value || ($after !== null && strcmp($record->id, $after->id) <= 0)) {
                    throw new LogicException('Index source did not advance in stable record order.');
                }
                $next = $page->next?->id;
            } else {
                $seq = Value::integer($gen->confirmed_seq) + 1;

                if ($seq > Value::integer($lane->freeze_seq ?? $lane->last_allocated_seq)) {
                    return false;
                }
                $dirty = $db->table(LaneStore::DIRTY)->where('lane_key', $lane->lane_key)->where('seq', $seq)->firstOrFail();
                $record = new RecordRef($entity->value, Value::text($dirty->record_id));
            }
        }
        $documents = [];

        if ($record !== null) {
            [$mapping] = ($this->projection)()->witnesses($scope, $entity);

            if ($mapping !== $gen->mapping_digest) {
                throw new LogicException('Search index mapping changed.');
            }
            $document = ($this->projection)()->document($scope, $entity, $record);
            $action = $document === null ? IndexAction::DeleteRecords : IndexAction::Upsert;
            $documents[] = $document ?? new IndexDocument($record, new ProjectedValues([]));
        }
        $batch = new IndexBatch($this->store->target($scope, $entity, $gen), (string) Str::uuid7(), $action, $documents);
        $claim = Value::integer($gen->claim_epoch) + 1;
        $db->table('corex_search_submissions')->insert([
            'lane_key' => $lane->lane_key, 'generation' => $gen->generation, 'uid' => $gen->uid,
            'submission_id' => $batch->submissionId, 'claim_epoch' => $claim, 'state' => 'planned', 'action' => $action->value,
            'deployment_id' => $scope->deploymentId, 'binding_id' => $scope->bindingId, 'binding_generation' => $scope->bindingGeneration,
            'restore_epoch' => $scope->restoreEpoch, 'first_seq' => $seq, 'last_seq' => $seq, 'scan_next' => $next,
            'batch_digest' => Submission::digest($batch), 'documents' => json_encode(Submission::documents($batch), JSON_THROW_ON_ERROR)]);
        LaneStore::one(LaneStore::generation($db, $lane, Value::integer($gen->generation))->where('claim_epoch', $gen->claim_epoch)->update(['claim_epoch' => $claim]));

        return true;
    }

    public static function finish(ConnectionInterface $db, stdClass $lane, Submission $work, IndexOutcome $outcome): void
    {
        $row = self::query($db, $work->row)->lockForUpdate()->firstOrFail();
        $genQuery = LaneStore::generation($db, $lane, $work->batch->target->generation)->where('uid', $row->uid)->where('claim_epoch', $row->claim_epoch);

        if (! in_array($row->state, ['sending', 'accepted'], true)) {
            return;
        }
        $gen = (clone $genQuery)->lockForUpdate()->firstOrFail();
        $state = match ($outcome) {
            IndexOutcome::Confirmed => 'confirmed',
            IndexOutcome::Failed => 'failed',
            IndexOutcome::Unknown => 'unknown',
            IndexOutcome::Pending => throw new LogicException('Cannot finalize a pending index submission.'),
        };
        LaneStore::one(self::query($db, $row)->where('state', $row->state)->update(['state' => $state]));

        if ($gen->state === 'quarantined') {
            return;
        }

        if ($outcome === IndexOutcome::Unknown || $outcome === IndexOutcome::Failed) {
            $nextState = $outcome === IndexOutcome::Unknown ? 'quarantined' : match ($gen->state) {
                'active', 'retired' => 'failed',
                'retained' => 'retained',
                'catching-up' => $gen->retain_until === null ? 'retired' : 'retained',
                default => 'retired',
            };
            LaneStore::one($genQuery->update(['state' => $nextState]));

            if ($lane->rebuild_id !== null && $db->table(LaneStore::RUNS)->where('rebuild_id', $lane->rebuild_id)->where('to_generation', $gen->generation)->exists()) {
                RebuildJournal::release($db, $lane, Value::text($lane->rebuild_id), 'failed');
            }

            return;
        }

        if ($work->batch->action === IndexAction::Drop) {
            LaneStore::one($genQuery->where('state', 'retired')->update(['state' => 'removed']));
        } elseif ($gen->state === 'retired') {
            return;
        } elseif ($work->batch->action === IndexAction::Create) {
            LaneStore::one($genQuery->where('state', 'allocated')->update(['state' => 'scanning']));
        } elseif ($row->first_seq !== null) {
            $before = Value::integer($row->first_seq) - 1;
            LaneStore::one($genQuery->where('confirmed_seq', $before)->update(['confirmed_seq' => $row->last_seq]));

            if ($gen->state === 'active') {
                LaneStore::one($db->table('corex_search_lanes')->where('lane_key', $lane->lane_key)->where('writer_generation', $gen->generation)
                    ->where('next_seq', $row->first_seq)->update(['next_seq' => Value::integer($row->last_seq) + 1]));
            }
        } elseif ($row->scan_next !== null) {
            LaneStore::one($genQuery->where('state', 'scanning')->update(['scan_cursor' => $row->scan_next]));
        } else {
            self::scanDone($db, $lane, $gen);
        }
    }

    private static function scanDone(ConnectionInterface $db, stdClass $lane, stdClass $gen): void
    {
        $run = $db->table('corex_search_rebuilds')->where('lane_key', $lane->lane_key)->where('rebuild_id', $lane->rebuild_id)->where('to_generation', $gen->generation)->where('state', 'scanning');
        $row = (clone $run)->lockForUpdate()->firstOrFail();
        LaneStore::one(LaneStore::generation($db, $lane, Value::integer($gen->generation))->where('state', 'scanning')->where('claim_epoch', $gen->claim_epoch)
            ->update(['state' => 'catching-up', 'confirmed_seq' => $row->start_seq]));
        LaneStore::one($run->update(['state' => 'catching-up']));
    }

    public static function query(ConnectionInterface $db, stdClass $row): Builder
    {
        return $db->table(LaneStore::SUBMISSIONS)->where('lane_key', $row->lane_key)->where('generation', $row->generation)
            ->where('submission_id', $row->submission_id)->where('claim_epoch', $row->claim_epoch);
    }
}
