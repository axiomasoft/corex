<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;
use stdClass;

#[Internal]
final readonly class SchemaWorker
{
    /** @param Closure():SchemaSteps $steps */
    public function __construct(private DatabaseManager $database, private TenantContextResolver $contexts, private DataScopeFactory $scopes,
        private WorkScopeRunner $runner, private StoredWorkRunner $stored, private CurrentWorkScope $current, private DataScopeCodec $codec,
        private SchemaJournal $journal, private ClockInterface $clock, private Closure $steps) {}

    public function one(): bool
    {
        $sweep = $this->scopes->forCurrentPrincipal($this->contexts->current());

        if ($sweep->principalKind !== 'service') {
            throw new FieldFailure(CustomFieldsErrorCode::AccessDenied);
        }
        $row = $this->runner->run(scope: $sweep, work: fn (): ?stdClass => $this->claim($sweep));

        if ($row === null) {
            return false;
        }
        $pinned = null;

        try {
            $scope = $this->codec->decode(SchemaRows::decode($row->scope));

            if (SchemaRows::logical($sweep) !== SchemaRows::logical($scope) || SchemaRows::fence($sweep) !== SchemaRows::fence($scope)) {
                throw new ScopeContextMismatch('Claimed field schema work changed its physical or logical owner.');
            }
            $proof = new StoredWorkProof(owner: SchemaJournal::OWNER, reference: SchemaJournal::proofReference($row), seal: SchemaValue::text($row->scope_seal));
            $key = new OperationKey(id: SchemaValue::text($row->id), attempt: SchemaValue::integer($row->generation), effectKey: SchemaValue::text($row->claim_token),
                deadline: new DateTimeImmutable(SchemaValue::text($row->lease_until)));
            $this->stored->runStored(scope: $scope, proof: $proof, work: function () use ($scope, $row, &$pinned): void {
                $steps = ($this->steps)();

                if (in_array($row->phase, ['index', 'contract'], true) || $row->cancel_requested === true) {
                    $pinned = $this->database->connection();
                }
                $steps->run($scope, $row);
            }, attempt: $key);
        } catch (SchemaClaimLost) {
            // Reclamation can lawfully win while the old server completes CIC.
        } catch (SchemaLockBusy) {
            $this->report(sweep: $sweep, row: $row, state: SchemaValue::text($row->state), blockers: []);
        } catch (SchemaConflict $conflict) {
            $this->report(sweep: $sweep, row: $row, state: 'blocked', blockers: [$conflict->getMessage()]);
        } catch (ScopeContextMismatch|ScopeStale|FieldFailure $failure) {
            $this->report(sweep: $sweep, row: $row, state: 'blocked', blockers: ['work-scope-or-capability-unavailable']);
        } catch (QueryException $failure) {
            $this->report(sweep: $sweep, row: $row, state: 'failed', blockers: ['postgresql-'.$failure->getCode()]);
        } finally {
            // The command owns this connection. Release only the same cached instance, after Core restores the outer frame.
            if ($pinned !== null) {
                $name = $pinned->getName();

                if (is_string($name) && ($this->database->getConnections()[$name] ?? null) === $pinned) {
                    $this->database->purge($name);
                } else {
                    $pinned->disconnect();
                }
            }
        }

        return true;
    }

    /** @param list<string> $blockers */
    private function report(DataScope $sweep, stdClass $row, string $state, array $blockers): void
    {
        $this->runner->run(scope: $sweep, work: function () use ($sweep, $row, $state, $blockers): void {
            $db = $this->database->connection();
            $this->current->assert(scope: $sweep, connection: $db);

            try {
                $db->transaction(fn () => $this->journal->advance(db: $db, scope: $sweep, claim: $row, state: $state, phase: SchemaValue::text($row->phase), values: ['blockers' => SchemaRows::json($blockers)]));
            } catch (SchemaClaimLost) {
                // The original token can never overwrite a successor's admission or receipt.
            }
        });
    }

    private function claim(DataScope $sweep): ?stdClass
    {
        $db = $this->database->connection();
        $this->current->assert(scope: $sweep, connection: $db);

        if ($db->getDriverName() !== 'pgsql' || $db->transactionLevel() !== 0) {
            throw new RuntimeException('A PostgreSQL field worker requires an independent, verified tenant connection.');
        }

        return $db->transaction(function () use ($db, $sweep): ?stdClass {
            $now = $this->clock->now();
            $query = $db->table('sys_attribute_schema_operations')->where(SchemaRows::logical($sweep))->where(SchemaRows::fence($sweep));
            $row = (clone $query)->whereIn('state', ['queued', 'expanding', 'backfilling', 'verifying', 'switched', 'contracting'])
                ->where('next_attempt_at', '<=', $now->format('Y-m-d H:i:s.uP'))
                ->where(static fn (Builder $due) => $due->whereNull('claim_token')->orWhere('lease_until', '<=', $now->format('Y-m-d H:i:s.uP')))
                ->orderBy('next_attempt_at')->orderBy('id')->lock('for update skip locked')->first();

            if ($row === null) {
                return null;
            }
            SchemaRows::one((clone $query)->where('id', $row->id)->where('generation', $row->generation)->update([
                'claim_token' => (string) Str::uuid7(), 'generation' => SchemaValue::integer($row->generation) + 1,
                'lease_until' => $now->modify(sprintf('+%d seconds', SchemaJournal::LEASE_SECONDS))->format('Y-m-d H:i:s.uP')]));

            return $query->where('id', $row->id)->first() ?? throw new SchemaClaimLost('Claim disappeared.');
        });
    }
}
