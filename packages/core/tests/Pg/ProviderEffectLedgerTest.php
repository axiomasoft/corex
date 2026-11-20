<?php

declare(strict_types=1);

use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\ProviderReceiptProjector;
use CoreX\Context\Contracts\ProviderRecoveryAuthority;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use CoreX\Context\Laravel\ScopeOptions;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../Support/Messaging.php';

function allowProviderJournalRecovery(ProviderEffectRef $allowed): void
{
    app()->instance(ProviderRecoveryAuthority::class, new class($allowed) implements ProviderRecoveryAuthority
    {
        public function __construct(private ProviderEffectRef $allowed) {}

        public function authorize(ProviderEffectRef $effect): void
        {
            if ($effect->identity() !== $this->allowed->identity()) {
                throw new ScopeContextMismatch('Unauthorized provider evidence.');
            }
        }
    });
}

beforeEach(function (): void {
    outboxRelayMigrate();
    $this->scope = messagingWorkScope();
    $binding = resolve(DatabaseScopeControl::class)->resolve(resolve(TenancyManager::class)->context()->account);
    $pg = DB::connection('pgsql');
    $this->journalDatabase = 'corex_journal_'.bin2hex(random_bytes(6)).'_test';
    $pg->statement('CREATE DATABASE '.$this->journalDatabase);
    config(['database.connections.provider_journal' => [...$pg->getConfig(), 'database' => $this->journalDatabase],
        'corex.scope.control_connection' => 'provider_journal']);
    app()->forgetInstance(ScopeOptions::class);
    $journal = DB::connection('provider_journal');
    expect($journal->selectOne('select current_database() as name')->name)->toBe($this->journalDatabase)
        ->and($pg->selectOne('select current_database() as name')->name)->not->toBe($this->journalDatabase);
    ScopeControlSchema::install($journal);
    app()->instance(ControlStoreIsolation::class, new class($journal) implements ControlStoreIsolation
    {
        public function __construct(private ConnectionInterface $journal) {}

        public function verify(ConnectionInterface $control, string $deploymentId): void
        {
            if ($control !== $this->journal || $deploymentId !== 'test-installation') {
                throw new ScopeContextMismatch('Unexpected test journal.');
            }
        }
    });
    app()->forgetScopedInstances();
    resolve(DatabaseScopeControl::class)->register($this->scope->accountId, $binding);
    $this->effect = new ProviderEffectRef($this->scope->accountId, $this->scope->accountIncarnation, $this->scope->workspaceId, 'delivery', 'sandbox-provider', 'stable-effect-key');
    $this->attempt = fn (int $attempt, bool $singleInstanceReference = false): ProviderAttempt => new ProviderAttempt($this->effect, 'owner-row-1', 'claim-'.$attempt, $attempt, new DateTimeImmutable('+2 minutes'), hash('sha256', 'immutable input'), singleInstanceReference: $singleInstanceReference);
});
afterEach(function (): void {
    if (isset($this->journalDatabase)) {
        DB::purge('provider_journal');
        DB::connection('pgsql')->statement('DROP DATABASE IF EXISTS '.$this->journalDatabase);
    }
});
it('keeps observed provider acceptance outside tenant rollback and only recovers through the authorized receipt projector while closing', function (): void {
    DB::statement('create table effect_receipts (effect_key text primary key, receipt text not null)');
    $ledger = resolve(ProviderEffectLedger::class);
    $ticket = $ledger->reserve($this->scope, ($this->attempt)(1));
    expect($ledger->inspect($this->effect)->ticket->id)->toBe($ticket->id)
        ->and(fn () => $ledger->reserve($this->scope, ($this->attempt)(2)))->toThrow(ScopeContextMismatch::class);
    $admission = resolve(ScopeAdmission::class);
    DB::beginTransaction();
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Unknown, 'timeout-1', 'worker-1'));
    $admission->settle($ticket, new AttemptEvidence('unknown', 'timeout-1'));
    DB::rollBack();
    $control = resolve(DatabaseScopeControl::class);
    $closing = $control->close($this->scope->accountId, 1);
    expect(fn () => $control->drain($this->scope->accountId, $closing))->toThrow(ScopeContextMismatch::class)
        ->and(fn () => resolve(ProviderEffectRecovery::class)->recover($this->effect))->toThrow(ScopeContextMismatch::class);
    allowProviderJournalRecovery($this->effect);
    $projector = new class implements ProviderReceiptProjector
    {
        public int $lookups = 0;

        public function owner(): string
        {
            return 'delivery';
        }

        public function lookup(ProviderEffectStatus $status): ?ProviderObservation
        {
            $this->lookups++;

            return new ProviderObservation(ProviderObservationKind::Confirmed, 'provider-ref-1', 'provider-lookup');
        }

        public function project(ProviderEffectStatus $status): ?string
        {
            $db = DB::connection();

            return $db->transaction(function () use ($db, $status): string {
                expect(fn () => resolve(CurrentWorkScope::class)->assert($status->ticket->scope, $db))->toThrow(ScopeContextMismatch::class);
                resolve(ProviderEffectRecovery::class)->assertReceipt($status, $db);
                $db->table('effect_receipts')->insert(['effect_key' => $status->effect->effectKey, 'receipt' => $status->observation->reference]);

                return 'owner-receipt-1';
            });
        }
    };
    app()->instance('fixture.receipt-projector', $projector);
    app()->tag('fixture.receipt-projector', ProviderReceiptProjector::class);
    app()->forgetScopedInstances();
    expect(resolve(ProviderEffectRecovery::class)->recover($this->effect))->toBeTrue()
        ->and($projector->lookups)->toBe(1)
        ->and(DB::table('effect_receipts')->value('receipt'))->toBe('provider-ref-1')
        ->and(resolve(ProviderEffectLedger::class)->inspect($this->effect)->state)->toBe('reconciled')
        ->and(DB::connection('provider_journal')->table('corex_provider_observations')->count())->toBe(2)
        ->and($control->drain($this->scope->accountId, $closing))->toBe(3);
})->group('pg');

it('blocks replay until proven absent and rejects stale evidence downgrades', function (): void {
    $ledger = resolve(ProviderEffectLedger::class);
    $first = $ledger->reserve($this->scope, ($this->attempt)(1));
    $admission = resolve(ScopeAdmission::class);
    expect(fn () => $admission->settle($first, new AttemptEvidence('reconciled', 'no-proof')))->toThrow(ScopeContextMismatch::class);
    $absent = new ProviderObservation(ProviderObservationKind::Absent, 'no-send:claim-lost', 'owner');
    $ledger->observe($first, $absent);
    $admission->settle($first, new AttemptEvidence('reconciled', 'owner-absent'));
    $second = $ledger->reserve($this->scope, ($this->attempt)(2));
    $confirmed = new ProviderObservation(ProviderObservationKind::Confirmed, 'receipt:2', 'provider');
    $ledger->observe($second, $confirmed);
    $ledger->observe($second, $confirmed);
    $ledger->observe($second, new ProviderObservation(ProviderObservationKind::Unknown, 'timeout:2', 'late-sender'));
    expect($ledger->inspect($this->effect)->observation)->toEqual($confirmed)
        ->and($ledger->inspect($this->effect)->ticket->id)->toBe($second->id)->not->toBe($first->id)
        ->and(DB::connection('provider_journal')->table('corex_provider_observations')->where('ticket_id', $second->id)->count())->toBe(2);
    $ledger->observe($second, new ProviderObservation(ProviderObservationKind::Unknown, 'timeout:2', 'late-sender'));
    expect(DB::connection('provider_journal')->table('corex_provider_observations')->where('ticket_id', $second->id)->count())->toBe(2);
    $admission->settle($second, new AttemptEvidence('reconciled', 'owner-confirmed'));
    expect(fn () => $ledger->reserve($this->scope, ($this->attempt)(3)))->toThrow(ScopeContextMismatch::class);
})->group('pg');

it('freezes a stable effect when definitive observations contradict an earlier absence', function (): void {
    $ledger = resolve(ProviderEffectLedger::class);
    $admission = resolve(ScopeAdmission::class);
    $first = $ledger->reserve($this->scope, ($this->attempt)(1));
    $ledger->observe($first, new ProviderObservation(ProviderObservationKind::Absent, 'absent-1', 'lookup'));
    $admission->settle($first, new AttemptEvidence('reconciled', 'first-owner-receipt'));
    $second = $ledger->reserve($this->scope, ($this->attempt)(2));
    $ledger->observe($second, new ProviderObservation(ProviderObservationKind::Absent, 'absent-2', 'lookup'));
    $admission->settle($second, new AttemptEvidence('reconciled', 'second-owner-receipt'));
    $ledger->observe($first, new ProviderObservation(ProviderObservationKind::Confirmed, 'late-confirmation', 'provider'));
    expect($ledger->inspect($this->effect)->state)->toBe('conflicting')
        ->and($ledger->inspect($this->effect)->ticket->id)->toBe($first->id)
        ->and(fn () => $ledger->reserve($this->scope, ($this->attempt)(3)))->toThrow(ScopeContextMismatch::class)
        ->and(fn () => $admission->settle($first, new AttemptEvidence('isolated', 'no-operator')))->toThrow(ScopeContextMismatch::class)
        ->and(resolve(ProviderEffectRecovery::class)->recover($this->effect))->toBeFalse();
    $control = resolve(DatabaseScopeControl::class);
    $revision = $control->close($this->scope->accountId, 1);
    expect(fn () => $control->drain($this->scope->accountId, $revision))->toThrow(ScopeContextMismatch::class);
})->group('pg');

it('quarantines conflicting accepted coordinates during closing without another lookup or positive projection', function (): void {
    $ledger = resolve(ProviderEffectLedger::class);
    $ticket = $ledger->reserve($this->scope, ($this->attempt)(1));
    $first = new ProviderObservation(ProviderObservationKind::Accepted, 'run-A', 'original-send');
    $ledger->observe($ticket, $first);
    $ledger->observe($ticket, $first);
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Accepted, 'run-A', 'same-run-lookup'));
    expect($ledger->inspect($this->effect)->firstProviderRef)->toBe('run-A')
        ->and($ledger->inspect($this->effect)->state)->toBe('outstanding');
    $control = resolve(DatabaseScopeControl::class);
    $revision = $control->close($this->scope->accountId, 1);
    allowProviderJournalRecovery($this->effect);
    DB::statement('create table effect_holds (ticket text primary key, disposition text not null)');
    $projector = new class implements ProviderReceiptProjector
    {
        public int $lookups = 0;

        public int $projects = 0;

        public function owner(): string
        {
            return 'delivery';
        }

        public function lookup(ProviderEffectStatus $status): ?ProviderObservation
        {
            $this->lookups++;

            return new ProviderObservation(ProviderObservationKind::Accepted, 'run-B', 'verified-lookup');
        }

        public function project(ProviderEffectStatus $status): ?string
        {
            $this->projects++;
            expect($status->state)->toBe('conflicting')
                ->and($status->firstProviderRef)->toBe('run-A')
                ->and($status->observation->kind)->toBe(ProviderObservationKind::Unknown)
                ->and($status->observation->reference)->toBe('conflicting-provider-evidence');
            $db = DB::connection();
            $db->transaction(function () use ($db, $status): void {
                resolve(ProviderEffectRecovery::class)->assertReceipt($status, $db);
                $db->table('effect_holds')->updateOrInsert(['ticket' => $status->ticket->id], ['disposition' => 'quarantined']);
            });

            return 'quarantined-owner-row';
        }
    };
    app()->instance('fixture.conflict-projector', $projector);
    app()->tag('fixture.conflict-projector', ProviderReceiptProjector::class);
    $recovery = resolve(ProviderEffectRecovery::class);
    expect($recovery->recover($this->effect))->toBeFalse();
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Confirmed, 'run-A', 'provider'));
    expect($recovery->recover($this->effect))->toBeFalse()
        ->and($projector->lookups)->toBe(1)->and($projector->projects)->toBe(2)
        ->and(DB::table('effect_holds')->where('ticket', $ticket->id)->value('disposition'))->toBe('quarantined')
        ->and($ledger->inspect($this->effect)->state)->toBe('conflicting')
        ->and(fn () => $control->settle($ticket, new AttemptEvidence('reconciled', 'later-positive')))->toThrow(ScopeContextMismatch::class)
        ->and(fn () => $control->drain($this->scope->accountId, $revision))->toThrow(ScopeContextMismatch::class);
    $history = DB::connection('provider_journal')->table('corex_provider_observations')->where('ticket_id', $ticket->id);
    expect((clone $history)->where('kind', 'accepted')->orderBy('reference')->pluck('reference')->all())->toBe(['run-A', 'run-A', 'run-B'])
        ->and((clone $history)->where('kind', 'confirmed')->value('reference'))->toBe('run-A');
})->group('pg');

it('retains the original accepted operation after confirming a distinct receipt and blocks reopen on a later contradiction', function (): void {
    $ledger = resolve(ProviderEffectLedger::class);
    $ticket = $ledger->reserve($this->scope, ($this->attempt)(1));
    $scope = $this->scope;
    expect($ledger->inspect($this->effect)->firstProviderRef)->toBeNull();
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Accepted, 'operation-A', 'original-send'));
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Confirmed, 'receipt-42', 'operator'));
    $status = $ledger->inspect($this->effect);
    expect($status->observation->kind)->toBe(ProviderObservationKind::Confirmed)
        ->and($status->observation->reference)->toBe('receipt-42')
        ->and($status->firstProviderRef)->toBe('operation-A');
    $control = resolve(DatabaseScopeControl::class);
    $control->settle($ticket, new AttemptEvidence('reconciled', 'owner-receipt'));
    $closed = $control->drain($scope->accountId, $control->close($scope->accountId, 1));
    allowProviderJournalRecovery($this->effect);
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Accepted, 'operation-B', 'late-lookup'));
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Confirmed, 'receipt-42', 'operator'));
    $next = new PhysicalBinding($scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->accountIncarnation, str_repeat('b', 64));
    expect($ledger->inspect($this->effect)->state)->toBe('conflicting')
        ->and($ledger->inspect($this->effect)->firstProviderRef)->toBe('operation-A')
        ->and(fn () => $control->reopen($scope->accountId, $closed, $next, static fn () => true))->toThrow(ScopeContextMismatch::class)
        ->and(DB::connection('provider_journal')->table('corex_scope_routes')->where('account_id', $scope->accountId)->value('mode'))->toBe('closed');
})->group('pg');

it('checks provider coordinates across ordered positive observations', function (bool $single, ProviderObservationKind $first, ProviderObservationKind $next, bool $sameRef, bool $afterDrain): void {
    $ledger = resolve(ProviderEffectLedger::class);
    $control = resolve(DatabaseScopeControl::class);
    $scope = $this->scope;
    $effect = $this->effect;
    $ticket = $ledger->reserve($scope, ($this->attempt)(1, $single));
    $initial = new ProviderObservation($first, 'A', 'sender');
    $second = new ProviderObservation($next, $sameRef ? 'A' : 'B', 'lookup');
    $ledger->observe($ticket, $initial);
    $ledger->observe($ticket, $initial);
    $firstPins = $single || $first === ProviderObservationKind::Accepted;
    expect($ledger->inspect($effect)->firstProviderRef)->toBe($firstPins ? 'A' : null);
    $closed = null;

    if ($afterDrain) {
        $control->settle($ticket, new AttemptEvidence('reconciled', 'prior-owner-receipt'));
        $closed = $control->drain($scope->accountId, $control->close($scope->accountId, 1));
        expect(fn () => $ledger->observe($ticket, $second))->toThrow(ScopeContextMismatch::class);
        allowProviderJournalRecovery($effect);
    }

    $ledger->observe($ticket, $second);
    $ledger->observe($ticket, $second);
    $status = $ledger->inspect($effect);
    $expectedReference = $firstPins ? 'A' : ($next === ProviderObservationKind::Accepted ? $second->reference : null);
    expect($status->firstProviderRef)->toBe($expectedReference)
        ->and(DB::connection('provider_journal')->table('corex_provider_observations')->where('ticket_id', $ticket->id)->count())->toBe(2);

    $conflict = ! $sameRef && ($single || ($first === ProviderObservationKind::Accepted && $next === ProviderObservationKind::Accepted));

    if ($conflict) {
        $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Confirmed, 'A', 'later-receipt'));
        expect($ledger->inspect($effect)->state)->toBe('conflicting')
            ->and($ledger->inspect($effect)->observation->kind)->toBe(ProviderObservationKind::Unknown)
            ->and(fn () => $control->settle($ticket, new AttemptEvidence('reconciled', 'later-receipt')))->toThrow(ScopeContextMismatch::class);
    } else {
        expect($status->state)->toBe($afterDrain ? 'reconciled' : 'outstanding');
        $expectedKind = in_array(ProviderObservationKind::Confirmed, [$first, $next], true) ? ProviderObservationKind::Confirmed : ProviderObservationKind::Accepted;
        expect($status->observation->kind)->toBe($expectedKind);
    }

    if ($closed !== null) {
        $replacement = new PhysicalBinding($scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->accountIncarnation, str_repeat('b', 64));
        $reopen = fn () => $control->reopen($scope->accountId, $closed, $replacement, static fn () => true);

        if ($conflict) {
            expect($reopen)->toThrow(ScopeContextMismatch::class);
        } else {
            expect($reopen())->toBe($closed + 1);
        }
    }
})->with(['generic' => false, 'single instance' => true])
    ->with(['first accepted' => ProviderObservationKind::Accepted, 'first confirmed' => ProviderObservationKind::Confirmed])
    ->with(['next accepted' => ProviderObservationKind::Accepted, 'next confirmed' => ProviderObservationKind::Confirmed])
    ->with(['same coordinate' => true, 'different coordinate' => false])
    ->with(['active sender' => false, 'after drain' => true])->group('pg');

it('preserves the reference policy when a proven-absent attempt is retried', function (bool $single): void {
    $ledger = resolve(ProviderEffectLedger::class);
    $ticket = $ledger->reserve($this->scope, ($this->attempt)(1, $single));
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Unknown, 'ambiguous', 'original-send'));
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Absent, 'absence-proof', 'operator'));
    resolve(ScopeAdmission::class)->settle($ticket, new AttemptEvidence('reconciled', 'owner-absence'));
    expect($ledger->inspect($this->effect)->firstProviderRef)->toBeNull()
        ->and(fn () => $ledger->reserve($this->scope, ($this->attempt)(2, ! $single)))->toThrow(ScopeContextMismatch::class);
    $second = $ledger->reserve($this->scope, ($this->attempt)(2, $single));
    $ledger->observe($second, new ProviderObservation(ProviderObservationKind::Confirmed, 'confirmed-operation', 'sender'));
    expect($ledger->inspect($this->effect)->firstProviderRef)->toBe($single ? 'confirmed-operation' : null)
        ->and(DB::connection('provider_journal')->table('corex_scope_tickets')->where('provider_identity', $this->effect->identity())->count())->toBe(2);
})->with(['generic' => false, 'single instance' => true])->group('pg');
