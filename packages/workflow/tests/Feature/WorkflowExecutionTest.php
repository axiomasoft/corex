<?php

declare(strict_types=1);

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Workflow\Contracts\EngineAdapter;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\StartResult;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\StartOutcome;
use CoreX\Workflow\Tests\TestCase;
use CoreX\Workflow\Tests\TestRecoveryAuthority;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

uses(TestCase::class);

it('starts a committed intent once and recovers the original provider reference after a lost outcome', function (): void {
    $clock = new FrozenClock(new DateTimeImmutable('2026-11-20T00:00:00Z'));
    app()->instance(ClockInterface::class, $clock);
    $engine = resolve(EngineAdapter::class);
    $engine->startOutcome = new StartResult(StartOutcome::Unknown);
    $engine->lookupOutcome = new StartResult(StartOutcome::Unknown);
    $context = resolve(TenantContextResolver::class)->current();
    $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal($context);
    $request = new TriggerRequest(
        $context, $scope, 'axiomasoft/corex-workflow', 'test.ticket', 'event:1', 'created', 'ticket:1',
        'definition:1', 'version:1', JsonDocuments::capture(['flag' => true]), 'committed-start',
    );

    $db = DB::connection();
    $capture = $db->transaction(fn () => resolve(WorkflowBinding::class)->capture($request, $db));
    $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
    $row = DB::table('sys_workflow_intents')->where('id', $capture->intentRef)->first();
    expect($row->state)->toBe('unknown')
        ->and($row->provider_ticket)->toBeString()
        ->and($engine->starts)->toHaveCount(1)
        ->and($engine->starts[0]->effectKey)->toBe($row->effect_key);

    $clock->advance(new DateInterval('PT16S'));
    $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
    expect(DB::table('sys_workflow_intents')->where('id', $capture->intentRef)->value('state'))->toBe('unknown')
        ->and($engine->starts)->toHaveCount(1);

    $engine->lookupOutcome = new StartResult(StartOutcome::Confirmed, 'engine:original-coordinate');
    $clock->advance(new DateInterval('PT16S'));
    $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
    $confirmed = DB::table('sys_workflow_intents')->where('id', $capture->intentRef)->first();
    expect($confirmed->state)->toBe('confirmed')
        ->and($confirmed->provider_instance)->toBe('engine:original-coordinate')
        ->and($confirmed->provider_ticket)->toBe($row->provider_ticket)
        ->and($engine->starts)->toHaveCount(1);
});

it('recovers an original receipt while its route is closing through the authorized projector', function (): void {
    $engine = resolve(EngineAdapter::class);
    $engine->startOutcome = new StartResult(StartOutcome::Unknown);
    $context = resolve(TenantContextResolver::class)->current();
    $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal($context);
    $request = new TriggerRequest(
        $context, $scope, 'axiomasoft/corex-workflow', 'test.ticket', 'event:2', 'created', 'ticket:2',
        'definition:1', 'version:1', JsonDocuments::capture(['flag' => true]), 'closing-start',
    );
    $db = DB::connection();
    $capture = $db->transaction(fn () => resolve(WorkflowBinding::class)->capture($request, $db));
    $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
    $row = DB::table('sys_workflow_intents')->where('id', $capture->intentRef)->first();
    $effect = new ProviderEffectRef(
        $scope->accountId, $scope->accountIncarnation, $scope->workspaceId, 'corex.workflow', 'fixture.workflow', $row->effect_key,
    );
    $control = resolve(DatabaseScopeControl::class);
    $revision = $control->close($scope->accountId, 1);
    expect(fn () => $control->drain($scope->accountId, $revision))->toThrow(ScopeContextMismatch::class)
        ->and($row->state)->toBe('unknown');

    $recovery = resolve(ProviderEffectRecovery::class);
    expect(fn () => $recovery->recover($effect))->toThrow(ScopeContextMismatch::class);
    resolve(TestRecoveryAuthority::class)->allowed = true;
    $engine->lookupOutcome = new StartResult(StartOutcome::Confirmed, 'engine:closing-receipt');
    expect($recovery->recover($effect))->toBeTrue()
        ->and(DB::table('sys_workflow_intents')->where('id', $capture->intentRef)->value('provider_instance'))->toBe('engine:closing-receipt')
        ->and($engine->starts)->toHaveCount(1)
        ->and($control->drain($scope->accountId, $revision))->toBe($revision + 1);
});

it('quarantines contradictory confirmation and keeps the original accepted engine instance', function (): void {
    $clock = new FrozenClock(new DateTimeImmutable('2026-11-20T00:00:00Z'));
    app()->instance(ClockInterface::class, $clock);
    $engine = resolve(EngineAdapter::class);
    $engine->startOutcome = new StartResult(StartOutcome::Accepted, 'engine:run-A');
    $engine->lookupOutcome = new StartResult(StartOutcome::Confirmed, 'engine:run-B');
    $context = resolve(TenantContextResolver::class)->current();
    $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal($context);
    $request = new TriggerRequest(
        $context, $scope, 'axiomasoft/corex-workflow', 'test.ticket', 'event:3', 'created', 'ticket:3',
        'definition:1', 'version:1', JsonDocuments::capture(['flag' => true]), 'contradictory-start',
    );

    $db = DB::connection();
    $capture = $db->transaction(fn () => resolve(WorkflowBinding::class)->capture($request, $db));
    $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
    $accepted = DB::table('sys_workflow_intents')->where('id', $capture->intentRef)->first();
    expect($accepted->state)->toBe('accepted')->and($accepted->provider_instance)->toBe('engine:run-A');

    $clock->advance(new DateInterval('PT16S'));
    $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
    $quarantined = DB::table('sys_workflow_intents')->where('id', $capture->intentRef)->first();
    expect($quarantined->state)->toBe('quarantined')
        ->and($quarantined->provider_instance)->toBe('engine:run-A')
        ->and($quarantined->provider_receipt)->toBe('engine:run-A')
        ->and($engine->starts)->toHaveCount(1);

    $journal = DB::connection('work-scope-control')->table('corex_provider_observations')->where('ticket_id', $quarantined->provider_ticket);
    expect($journal->pluck('reference')->sort()->values()->all())->toBe(['engine:run-A', 'engine:run-B']);
    $control = resolve(DatabaseScopeControl::class);
    $revision = $control->close($scope->accountId, 1);
    expect(fn () => $control->drain($scope->accountId, $revision))->toThrow(ScopeContextMismatch::class);
});

it('pins acceptance before reconciling when the accepted projection was lost', function (bool $closing, string $confirmedRef, bool $directJournal = false): void {
    $clock = new FrozenClock(new DateTimeImmutable('2026-11-20T00:00:00Z'));
    app()->instance(ClockInterface::class, $clock);
    $engine = resolve(EngineAdapter::class);
    $engine->startOutcome = new StartResult(StartOutcome::Accepted, 'engine:run-A');
    $engine->lookupOutcome = new StartResult(StartOutcome::Confirmed, $confirmedRef);
    $context = resolve(TenantContextResolver::class)->current();
    $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal($context);
    $request = new TriggerRequest(
        $context, $scope, 'axiomasoft/corex-workflow', 'test.ticket', 'event:4', 'created', 'ticket:4',
        'definition:1', 'version:1', JsonDocuments::capture(['flag' => true]), 'unprojected-acceptance',
    );
    $db = DB::connection();
    $capture = $db->transaction(fn () => resolve(WorkflowBinding::class)->capture($request, $db));
    $intent = $db->table('sys_workflow_intents')->where('id', $capture->intentRef);
    $postgres = $db->getDriverName() === 'pgsql';

    if ($postgres) {
        $db->unprepared(<<<'SQL'
            CREATE FUNCTION skip_workflow_acceptance_projection() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RETURN NULL;
            END;
            $$;
            CREATE TRIGGER skip_workflow_acceptance_projection BEFORE UPDATE ON sys_workflow_intents
                FOR EACH ROW WHEN (NEW.state = 'accepted') EXECUTE FUNCTION skip_workflow_acceptance_projection();
            SQL);
    } else {
        $db->unprepared(<<<'SQL'
            CREATE TRIGGER skip_workflow_acceptance_projection BEFORE UPDATE ON sys_workflow_intents
                WHEN NEW.state = 'accepted' BEGIN SELECT RAISE(IGNORE); END;
            SQL);
    }

    try {
        $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
    } finally {
        if ($postgres) {
            $db->unprepared('DROP TRIGGER skip_workflow_acceptance_projection ON sys_workflow_intents; DROP FUNCTION skip_workflow_acceptance_projection();');
        } else {
            $db->unprepared('DROP TRIGGER skip_workflow_acceptance_projection');
        }
    }

    $unprojected = $intent->first();
    $journal = DB::connection('work-scope-control')->table('corex_provider_observations')->where('ticket_id', $unprojected->provider_ticket);
    expect($unprojected->state)->toBe('sending')
        ->and($unprojected->provider_instance)->toBeNull()
        ->and($journal->pluck('reference', 'kind')->all())->toBe(['accepted' => 'engine:run-A']);

    $effect = new ProviderEffectRef($scope->accountId, $scope->accountIncarnation, $scope->workspaceId, 'corex.workflow', 'fixture.workflow', $unprojected->effect_key);
    $ledger = resolve(ProviderEffectLedger::class);
    $ticket = $directJournal ? $ledger->inspect($effect)->ticket : null;
    $confirmed = new ProviderObservation(ProviderObservationKind::Confirmed, $confirmedRef, 'authorized-operator');
    $control = resolve(DatabaseScopeControl::class);
    $clock->advance(new DateInterval('PT61S'));
    $matches = $confirmedRef === 'engine:run-A';

    if ($closing) {
        $revision = $control->close($scope->accountId, 1);

        if ($ticket !== null) {
            expect(fn () => $ledger->observe($ticket, $confirmed))->toThrow(ScopeContextMismatch::class);
        }

        resolve(TestRecoveryAuthority::class)->allowed = true;
    }

    if ($ticket !== null) {
        $ledger->observe($ticket, $confirmed);
    }

    if ($closing) {
        expect(resolve(ProviderEffectRecovery::class)->recover($effect))->toBe($matches);
    } else {
        $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
        $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
        $clock->advance(new DateInterval('PT16S'));
        $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
        $revision = $control->close($scope->accountId, 1);
    }

    $row = $intent->first();
    expect($row->state)->toBe($matches ? 'confirmed' : 'quarantined')
        ->and($row->provider_instance)->toBe('engine:run-A')
        ->and($row->provider_receipt)->toBe('engine:run-A')
        ->and($row->provider_ticket)->toBe($unprojected->provider_ticket)
        ->and($journal->orderBy('kind')->pluck('reference', 'kind')->all())->toBe(['accepted' => 'engine:run-A', 'confirmed' => $confirmedRef])
        ->and($engine->starts)->toHaveCount(1)
        ->and($engine->lookups)->toHaveCount($directJournal ? 0 : 1);

    if (! $directJournal) {
        expect($engine->lookups[0]->instanceRef)->toBe('engine:run-A');
    }

    if ($matches) {
        expect($control->drain($scope->accountId, $revision))->toBe($revision + 1);
    } else {
        expect(fn () => $control->drain($scope->accountId, $revision))->toThrow(ScopeContextMismatch::class);
    }
})->with([
    'open route with a matching confirmation' => [false, 'engine:run-A'],
    'open route with a contradictory confirmation' => [false, 'engine:run-B'],
    'closing route with a matching confirmation' => [true, 'engine:run-A'],
    'closing route with a contradictory confirmation' => [true, 'engine:run-B'],
    'open route with conflicting confirmation already journaled' => [false, 'engine:run-B', true],
    'closing route with conflicting confirmation already journaled' => [true, 'engine:run-B', true],
]);

it('fences contradictory acceptance after a later confirmation of the original run', function (bool $closing, string $acceptedRef, bool $lateConflict = false): void {
    $clock = new FrozenClock(new DateTimeImmutable('2026-11-20T00:00:00Z'));
    app()->instance(ClockInterface::class, $clock);
    $engine = resolve(EngineAdapter::class);
    $engine->startOutcome = new StartResult(StartOutcome::Accepted, 'engine:run-A');
    $engine->lookupOutcome = new StartResult(StartOutcome::Accepted, $acceptedRef);
    $context = resolve(TenantContextResolver::class)->current();
    $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal($context);
    $request = new TriggerRequest(
        $context, $scope, 'axiomasoft/corex-workflow', 'test.ticket', 'event:5', 'created', 'ticket:5',
        'definition:1', 'version:1', JsonDocuments::capture(['flag' => true]), 'repeated-acceptance',
    );
    $db = DB::connection();
    $capture = $db->transaction(fn () => resolve(WorkflowBinding::class)->capture($request, $db));
    $intent = $db->table('sys_workflow_intents')->where('id', $capture->intentRef);
    $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
    $accepted = $intent->first();
    $effect = new ProviderEffectRef($scope->accountId, $scope->accountIncarnation, $scope->workspaceId, 'corex.workflow', 'fixture.workflow', $accepted->effect_key);
    $control = resolve(DatabaseScopeControl::class);
    $ledger = resolve(ProviderEffectLedger::class);
    $ticket = $ledger->inspect($effect)->ticket;
    $recovery = resolve(ProviderEffectRecovery::class);
    resolve(TestRecoveryAuthority::class)->allowed = true;
    $clock->advance(new DateInterval('PT16S'));
    $matches = $acceptedRef === 'engine:run-A';

    if ($closing) {
        $revision = $control->close($scope->accountId, 1);
        expect($recovery->recover($effect))->toBeFalse();
    } else {
        $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
    }

    expect($intent->value('state'))->toBe($matches ? 'accepted' : 'quarantined');
    $engine->lookupOutcome = new StartResult(StartOutcome::Confirmed, 'engine:run-A');
    $clock->advance(new DateInterval('PT16S'));

    if (! $matches) {
        $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Confirmed, 'engine:run-A', 'authorized-operator'));
    }

    if ($closing) {
        expect($recovery->recover($effect))->toBe($matches);
    } else {
        $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
        $revision = $control->close($scope->accountId, 1);
    }

    if ($lateConflict) {
        expect($intent->value('state'))->toBe('confirmed');
        $ledger->observe($ticket, new ProviderObservation(ProviderObservationKind::Accepted, 'engine:run-B', 'authorized-operator'));
        expect($recovery->recover($effect))->toBeFalse();
    }

    $conflicting = ! $matches || $lateConflict;
    $status = $ledger->inspect($effect);
    $row = $intent->first();
    $journal = DB::connection('work-scope-control')->table('corex_provider_observations')->where('ticket_id', $row->provider_ticket);
    expect($status->state)->toBe($conflicting ? 'conflicting' : 'reconciled')
        ->and($status->firstProviderRef)->toBe('engine:run-A')
        ->and($row->state)->toBe($conflicting ? 'quarantined' : 'confirmed')
        ->and($row->provider_instance)->toBe('engine:run-A')
        ->and($row->provider_receipt)->toBe('engine:run-A')
        ->and($row->provider_ticket)->toBe($accepted->provider_ticket)
        ->and($journal->orderBy('kind')->orderBy('reference')->pluck('reference')->all())->toBe(
            $conflicting ? ['engine:run-A', 'engine:run-B', 'engine:run-A'] : ['engine:run-A', 'engine:run-A'],
        )
        ->and($engine->starts)->toHaveCount(1)
        ->and($engine->lookups)->toHaveCount($matches ? 2 : 1);

    if ($conflicting) {
        expect($status->observation->kind)->toBe(ProviderObservationKind::Unknown)
            ->and(fn () => $control->drain($scope->accountId, $revision))->toThrow(ScopeContextMismatch::class);
    } else {
        expect($control->drain($scope->accountId, $revision))->toBe($revision + 1);
    }
})->with([
    'open route with matching acceptance' => [false, 'engine:run-A'],
    'open route with contradictory acceptance' => [false, 'engine:run-B'],
    'closing route with matching acceptance' => [true, 'engine:run-A'],
    'closing route with contradictory acceptance' => [true, 'engine:run-B'],
    'closing route with acceptance contradicted after confirmation' => [true, 'engine:run-A', true],
]);

it('fences a closed route if a late observation identifies another engine run', function (StartOutcome $initial, ProviderObservationKind $late, string $lateRef): void {
    $clock = new FrozenClock(new DateTimeImmutable('2026-11-20T00:00:00Z'));
    app()->instance(ClockInterface::class, $clock);
    $engine = resolve(EngineAdapter::class);
    $engine->startOutcome = new StartResult($initial, 'engine:run-A');
    $engine->lookupOutcome = new StartResult(StartOutcome::Confirmed, 'engine:run-A');
    $context = resolve(TenantContextResolver::class)->current();
    $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal($context);
    $request = new TriggerRequest(
        $context, $scope, 'axiomasoft/corex-workflow', 'test.ticket', 'event:6', 'created', 'ticket:6',
        'definition:1', 'version:1', JsonDocuments::capture(['flag' => true]), 'closed-route-coordinate',
    );
    $db = DB::connection();
    $capture = $db->transaction(fn () => resolve(WorkflowBinding::class)->capture($request, $db));
    $intent = $db->table('sys_workflow_intents')->where('id', $capture->intentRef);
    $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();

    if ($initial === StartOutcome::Accepted) {
        $clock->advance(new DateInterval('PT16S'));
        $this->artisan('corex:workflow:sweep', ['--limit' => 1])->assertSuccessful();
    }

    $confirmed = $intent->first();
    expect($confirmed->state)->toBe('confirmed')
        ->and($confirmed->provider_instance)->toBe('engine:run-A');

    $ledger = resolve(ProviderEffectLedger::class);
    $effect = new ProviderEffectRef($scope->accountId, $scope->accountIncarnation, $scope->workspaceId, 'corex.workflow', 'fixture.workflow', $confirmed->effect_key);
    $before = $ledger->inspect($effect);
    $control = resolve(DatabaseScopeControl::class);
    $closing = $control->close($scope->accountId, 1);
    $closed = $control->drain($scope->accountId, $closing);
    $lateObservation = new ProviderObservation($late, $lateRef, 'authorized-operator');

    expect($before->state)->toBe('reconciled')
        ->and($before->firstProviderRef)->toBe('engine:run-A')
        ->and($control->resolve($context->account)->mode)->toBe('closed')
        ->and(fn () => $ledger->observe($before->ticket, $lateObservation))->toThrow(ScopeContextMismatch::class);

    resolve(TestRecoveryAuthority::class)->allowed = true;
    $ledger->observe($before->ticket, $lateObservation);
    $ledger->observe($before->ticket, $lateObservation);
    $after = $ledger->inspect($effect);
    $conflicting = $lateRef !== 'engine:run-A';
    $journal = DB::connection('work-scope-control')->table('corex_provider_observations')->where('ticket_id', $before->ticket->id);

    expect($after->state)->toBe($conflicting ? 'conflicting' : 'reconciled')
        ->and($after->firstProviderRef)->toBe('engine:run-A')
        ->and($after->observation->kind)->toBe($conflicting ? ProviderObservationKind::Unknown : ProviderObservationKind::Confirmed)
        ->and((clone $journal)->where('kind', 'confirmed')->where('reference', 'engine:run-A')->exists())->toBeTrue()
        ->and((clone $journal)->where('kind', $late->value)->where('reference', $lateRef)->exists())->toBeTrue()
        ->and((clone $journal)->where('reference', 'engine:run-B')->count())->toBe($conflicting ? 1 : 0)
        ->and((array) $intent->first())->toBe((array) $confirmed)
        ->and($engine->lookups)->toHaveCount($initial === StartOutcome::Accepted ? 1 : 0)
        ->and($engine->starts)->toHaveCount(1);

    $next = new PhysicalBinding($scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->accountIncarnation, str_repeat('b', 64));

    if ($conflicting) {
        expect(fn () => $control->reopen($scope->accountId, $closed, $next, static fn (): bool => true))->toThrow(ScopeContextMismatch::class)
            ->and($control->resolve($context->account)->mode)->toBe('closed')
            ->and($control->resolve($context->account)->restoreEpoch)->toBe($scope->restoreEpoch);
    } else {
        expect($control->reopen($scope->accountId, $closed, $next, static fn (): bool => true))->toBe($closed + 1)
            ->and($control->resolve($context->account)->restoreEpoch)->toBe($next->restoreEpoch);
    }
})->with([
    'first confirmation then conflicting confirmation' => [StartOutcome::Confirmed, ProviderObservationKind::Confirmed, 'engine:run-B'],
    'first confirmation then conflicting acceptance' => [StartOutcome::Confirmed, ProviderObservationKind::Accepted, 'engine:run-B'],
    'acceptance and confirmation then conflicting confirmation' => [StartOutcome::Accepted, ProviderObservationKind::Confirmed, 'engine:run-B'],
    'acceptance and confirmation then conflicting acceptance' => [StartOutcome::Accepted, ProviderObservationKind::Accepted, 'engine:run-B'],
    'first confirmation then duplicate confirmation' => [StartOutcome::Confirmed, ProviderObservationKind::Confirmed, 'engine:run-A'],
    'first confirmation then matching acceptance' => [StartOutcome::Confirmed, ProviderObservationKind::Accepted, 'engine:run-A'],
    'acceptance and confirmation then duplicate confirmation' => [StartOutcome::Accepted, ProviderObservationKind::Confirmed, 'engine:run-A'],
    'acceptance and confirmation then duplicate acceptance' => [StartOutcome::Accepted, ProviderObservationKind::Accepted, 'engine:run-A'],
]);
