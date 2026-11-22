<?php

declare(strict_types=1);

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderRecoveryAuthority;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Context\Exceptions\ScopeAuthorityUnavailable;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Support\Facades\DB;

it('upgrades the independent control store without discarding a route or ticket', function (): void {
    $scope = WorkScopeFixture::install(app())->scope();
    $owner = app(DatabaseScopeControl::class);
    $control = DB::connection('work-scope-control');
    $schema = $control->getSchemaBuilder();
    $ticket = $owner->acquire($scope, new OperationKey('before-lifecycle', 1), AdmissionKind::LocalWrite);
    $schema->dropColumns('corex_scope_routes', ['module_closures']);
    $schema->dropColumns('corex_scope_tickets', ['module_targets']);

    ScopeControlSchema::upgradeLifecycle($control);
    ScopeControlSchema::upgradeLifecycle($control);

    expect($schema->hasColumn('corex_scope_routes', 'module_closures'))->toBeTrue()
        ->and($schema->hasColumn('corex_scope_tickets', 'module_targets'))->toBeTrue()
        ->and($control->table('corex_scope_routes')->where('account_id', $scope->accountId)->value('restore_epoch'))->toBe($scope->restoreEpoch)
        ->and($control->table('corex_scope_tickets')->where('ticket_id', $ticket->id)->value('operation_id'))->toBe('before-lifecycle');

    $owner->settle($ticket, new AttemptEvidence('rolled-back', 'fixture-complete'));
});

it('drains an in-flight provider attempt before reopening a restored binding', function (): void {
    $fixture = WorkScopeFixture::install(app());
    $scope = $fixture->scope();
    $effect = new ProviderEffectRef($scope->accountId, $scope->accountIncarnation, $scope->workspaceId, 'delivery', 'provider', 'effect-1');
    app()->instance(ProviderRecoveryAuthority::class, new class($effect) implements ProviderRecoveryAuthority
    {
        public function __construct(private ProviderEffectRef $permitted) {}

        public function authorize(ProviderEffectRef $effect): void
        {
            if ($this->permitted->identity() !== $effect->identity()) {
                throw new ScopeContextMismatch('Unexpected fixture effect.');
            }
        }
    });
    $owner = app(DatabaseScopeControl::class);
    $ledger = app(ProviderEffectLedger::class);
    $ticket = $ledger->reserve($scope, new ProviderAttempt($effect, 'owner-row', 'claim', 1, new DateTimeImmutable('+1 minute'), hash('sha256', 'payload')));
    $closing = $owner->close($scope->accountId, 1);
    expect(fn () => $owner->acquire($scope, new OperationKey('after-close', 1), AdmissionKind::LocalWrite))->toThrow(ScopeContextMismatch::class);
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Unknown, 'provider-timeout', 'sender'));
    $owner->settle($ticket, new AttemptEvidence('unknown', 'provider-timeout'));
    expect(fn () => $owner->drain($scope->accountId, $closing))->toThrow(ScopeContextMismatch::class);
    $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Confirmed, 'provider-receipt-1', 'operator'));
    $owner->settle($ticket, new AttemptEvidence('reconciled', 'owner-receipt-1'));
    $closed = $owner->drain($scope->accountId, $closing);
    $next = new PhysicalBinding($scope->deploymentId, $scope->bindingId, 1, $scope->accountIncarnation, str_repeat('b', 64));
    expect(fn () => $owner->reopen($scope->accountId, $closed, $next, static fn () => false))->toThrow(ScopeContextMismatch::class);
    $owner->reopen($scope->accountId, $closed, $next, static fn () => true);
    $ran = false;
    expect(fn () => app(WorkScopeRunner::class)->run($scope, function () use (&$ran) {
        $ran = true;
    }))->toThrow(ScopeStale::class)
        ->and($ran)->toBeFalse()->and($fixture->scope()->restoreEpoch)->toBe(str_repeat('b', 64));
    $closed = $owner->drain($scope->accountId, $owner->close($scope->accountId, 4));
    $old = new PhysicalBinding($scope->deploymentId, $scope->bindingId, 1, $scope->accountIncarnation, $scope->restoreEpoch);
    expect(fn () => $owner->reopen($scope->accountId, $closed, $old, static fn () => true))->toThrow(ScopeContextMismatch::class);
});

it('preserves the caller transaction and holds its ticket until commit', function (): void {
    $scope = WorkScopeFixture::install(app())->scope();
    $owner = app(DatabaseScopeControl::class);
    $business = DB::connection();
    $business->beginTransaction();

    try {
        app(WorkScopeRunner::class)->run($scope, function () use ($scope, $business): void {
            expect(app(CurrentWorkScope::class)->assert($scope, $business)->account->id)->toBe($scope->accountId)
                ->and($business->transactionLevel())->toBe(1);
        });
        $closing = $owner->close($scope->accountId, 1);
        expect(fn () => $owner->drain($scope->accountId, $closing))->toThrow(ScopeContextMismatch::class);
        $business->commit();
        expect($owner->drain($scope->accountId, $closing))->toBe(3);
    } finally {
        if ($business->transactionLevel()) {
            $business->rollBack();
        }
    }
});

it('pins a direct transaction once and retains its ticket while a nested savepoint rolls back', function (): void {
    $scope = WorkScopeFixture::install(app())->scope();
    $db = DB::connection();
    $owner = app(DatabaseScopeControl::class);
    $db->beginTransaction();

    try {
        $db->beginTransaction();
        app(CurrentWorkScope::class)->assert($scope, $db);
        $closing = $owner->close($scope->accountId, 1);
        $db->rollBack();
        app(CurrentWorkScope::class)->assert($scope, $db);
        expect(DB::connection('work-scope-control')->table('corex_scope_tickets')->count())->toBe(1)
            ->and(fn () => $owner->drain($scope->accountId, $closing))->toThrow(ScopeContextMismatch::class);
        $db->rollBack();
        expect(DB::connection('work-scope-control')->table('corex_scope_tickets')->value('state'))->toBe('rolled-back')
            ->and($owner->drain($scope->accountId, $closing))->toBe(3);
    } finally {
        if ($db->transactionLevel()) {
            $db->rollBack(0);
        }
    }
});

it('refuses to issue an uncommitted ticket on a host-owned outer control transaction', function (): void {
    $scope = WorkScopeFixture::install(app())->scope();
    DB::connection('work-scope-control')->transaction(function () use ($scope): void {
        expect(fn () => app(DatabaseScopeControl::class)->acquire($scope, new OperationKey('pinned', 1), AdmissionKind::LocalWrite))
            ->toThrow(ScopeAuthorityUnavailable::class);
    });
});

it('rejects generic provider admission and freezes the owner linkage under a typed reservation', function (): void {
    $scope = WorkScopeFixture::install(app())->scope();
    $control = app(DatabaseScopeControl::class);
    $deadline = new DateTimeImmutable('+1 minute');
    expect(fn () => $control->acquire($scope, new OperationKey('send', 1, 'payment-1', $deadline), AdmissionKind::ProviderSend))->toThrow(ScopeContextMismatch::class)
        ->and(DB::connection('work-scope-control')->table('corex_scope_tickets')->count())->toBe(0);
    $effect = new ProviderEffectRef($scope->accountId, $scope->accountIncarnation, $scope->workspaceId, 'delivery', 'provider', 'payment-1');
    $ticket = app(ProviderEffectLedger::class)->reserve($scope, new ProviderAttempt($effect, 'payment-row', 'claim-1', 1, $deadline, hash('sha256', 'payment')));
    $row = DB::connection('work-scope-control')->table('corex_scope_tickets')->sole();
    expect($row->ticket_id)->toBe($ticket->id)->and($row->effect_key)->toBe('payment-1')->and($row->deadline)->not->toBeNull()->and($row->provider_identity)->toBe($effect->identity())->and($row->claim_token)->toBe('claim-1');
});

it('accepts identical completion evidence on retry and preserves the original receipt on conflict', function (): void {
    $scope = WorkScopeFixture::install(app())->scope();
    $control = app(DatabaseScopeControl::class);
    $ticket = $control->acquire($scope, new OperationKey('apply', 2), AdmissionKind::LocalWrite);
    $receipt = new AttemptEvidence('committed', 'receipt-2');
    $control->settle($ticket, $receipt);
    $control->settle($ticket, $receipt);
    expect(fn () => $control->settle($ticket, new AttemptEvidence('rolled-back', 'conflicting-receipt')))->toThrow(ScopeContextMismatch::class);
    $row = DB::connection('work-scope-control')->table('corex_scope_tickets')->sole();
    expect($row->state)->toBe('committed')->and($row->evidence)->toBe('receipt-2');
});

it('keeps admission closed when an earlier autocommit effect survives a later transaction rollback', function (): void {
    $scope = WorkScopeFixture::install(app())->scope();
    DB::statement('CREATE TABLE local_effects (label text NOT NULL)');
    $work = app(WorkScopeRunner::class);
    expect(fn () => $work->run($scope, static function (): void {
        DB::table('local_effects')->insert(['label' => 'committed']);
        DB::transaction(static function (): void {
            DB::table('local_effects')->insert(['label' => 'rolled-back']);

            throw new RuntimeException('local failure');
        });
    }))->toThrow(RuntimeException::class, 'local failure');
    expect(DB::table('local_effects')->pluck('label')->all())->toBe(['committed'])
        ->and(DB::connection('work-scope-control')->table('corex_scope_tickets')->sole()->state)->toBe('unknown');
    $control = app(DatabaseScopeControl::class);
    $closing = $control->close($scope->accountId, 1);
    expect(fn () => $control->drain($scope->accountId, $closing))->toThrow(ScopeContextMismatch::class);
});
