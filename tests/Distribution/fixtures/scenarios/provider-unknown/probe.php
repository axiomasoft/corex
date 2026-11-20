<?php

declare(strict_types=1);

use App\Scenario\FixtureNotificationDriver;
use App\Scenario\FixtureProviderRecoveryAuthority;
use App\Scenario\FixtureWebhookTransport;
use App\Scenario\ProviderSimulator;
use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use App\Scenario\ScenarioQueue;
use App\Scenario\ScenarioSetup;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Notifications\Delivery\Contracts\DeliveryExecution;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Webhooks\Contracts\WebhookSubscriptionStore;
use CoreX\Webhooks\Data\WebhookSubscription;
use CoreX\Webhooks\Internal\WebhookReplay;
use CoreX\Workflow\Contracts\WorkflowBinding;
use CoreX\Workflow\Data\TriggerRequest;
use CoreX\Workflow\Enums\CaptureStatus;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$database = Scenario::connection('a');
$control = Scenario::connection('control');
$clock = app(ScenarioClock::class);
$mode = $argv[1] ?? '';

function inWorkspace(Closure $work, string $actor = Scenario::USER_A): mixed
{
    return ScenarioQueue::inScope(Scenario::BOXED, Scenario::W1, $actor, static function () use ($work): mixed {
        $context = app(TenantContextResolver::class)->current();
        $scope = app(DataScopeFactory::class)->forCurrentPrincipal($context);

        return $work($context, $scope);
    });
}

function runCommand(Scenario $proof, string $command, array $arguments): string
{
    $proof->same(0, Artisan::call($command, $arguments), 'The installed command completes successfully');

    return trim(Artisan::output());
}

function ownerRow(Connection $database, string $owner, string $id): object
{
    $table = match ($owner) {
        'notifications' => 'ntf_deliveries',
        'webhooks' => 'sys_api_webhook_deliveries',
        'workflow' => 'sys_workflow_intents',
    };

    return $database->table($table)->selectRaw('*, xmin::text AS tuple_version')->where('id', $id)->firstOrFail();
}

function providerTicket(Connection $control, object $row): object
{
    return $control->table('corex_scope_tickets')->where('ticket_id', $row->provider_ticket)->firstOrFail();
}

function singleEffect(Scenario $proof, string $owner, string $key): array
{
    $journal = ProviderSimulator::evidence($owner, $key);
    $proof->same(1, count($journal['requests']), 'The independent journal records exactly one transport send');
    $proof->same(1, count($journal['effects']), 'The provider commits exactly one effect for the original key');

    return $journal;
}

function expectScopeDenial(Scenario $proof, Closure $work, string $description): void
{
    try {
        $work();
    } catch (ScopeContextMismatch) {
        $proof->check(true, $description);

        return;
    }

    throw new RuntimeException($description);
}

if ($mode === 'produce') {
    $migrations = ScenarioSetup::install('provider-unknown', false);
    $proof->check($migrations['a'] !== [], 'The installed owner schema sets migrate on the tenant target');
    $control->getSchemaBuilder()->create('scenario_broker_receipts', static function (Blueprint $table): void {
        $table->uuid('message_id')->primary();
        $table->char('digest', 64);
    });
    $control->getSchemaBuilder()->create('scenario_provider_operator_grants', static function (Blueprint $table): void {
        $table->uuid('operator_id');
        $table->uuid('account_id');
        $table->uuid('workspace_id');
        $table->string('owner');
        $table->string('provider_namespace');
        $table->string('effect_key');
    });
    $database->table('users')->insert(['id' => Scenario::USER_B, 'email' => 'operator@example.test', 'name' => 'Verified fixture operator']);
    $database->table('wsp_workspace_members')->insert(['id' => (string) Str::uuid7(), 'workspace_id' => Scenario::W1, 'user_id' => Scenario::USER_B, 'status' => 'active']);
    $refs = inWorkspace(static function ($context, $scope) use ($proof, $clock, $database): array {
        foreach (['axiomasoft/corex-notifications', 'axiomasoft/corex-workflow'] as $module) {
            app(ModuleLifecycle::class)->enable($module, cascade: true);
        }
        $notification = app(NotificationCenter::class)->notify(new NotificationContext($context, 'user', Scenario::USER_A), new NotificationInput(
            requestKey: 'provider-notification', eventType: 'fixture.notify', recipient: new RecipientReference('user', Scenario::USER_A),
            templateKey: 'fixture.notification', payload: JsonDocuments::capture(['scenario' => 'lost-ack']), related: null,
            channels: [FixtureNotificationDriver::CHANNEL],
        ));
        $now = $clock->now();
        $subscription = app(WebhookSubscriptionStore::class)->create($scope, new WebhookSubscription(
            'fixture-subscription', 0, 'v1', 'fixture.recordchanged', 1, 'https://'.FixtureWebhookTransport::HOST.'/hook',
            'active', 'none', [FixtureWebhookTransport::KEY], $now, $now,
        ), sensitiveAbility: false, policyAllows: true);
        $proof->same('fixture-subscription', $subscription->id, 'The published topic is subscribed through the installed store');
        $message = new MessageEnvelope(
            messageId: (string) Str::uuid7(), source: 'fixture', name: 'fixture.recordchanged', contractVersion: 1, occurredAt: $now,
            scope: $scope, payload: new MessagePayload(['record' => Scenario::RECORD]), piiClass: PiiClass::None,
            subject: new RecordRef('scenario.record', Scenario::RECORD), correlationId: 'fixture-webhook-fact',
        );
        $writer = DB::connection();
        $capture = $writer->transaction(static function () use ($writer, $context, $scope, $message) {
            app(Outbox::class)->append($writer, $message);

            return app(WorkflowBinding::class)->capture(new TriggerRequest(
                context: $context, scope: $scope, module: 'axiomasoft/corex-workflow', entityHandle: 'scenario.record',
                eventId: $message->messageId, eventName: 'record.changed', subjectRef: Scenario::RECORD, definitionRef: 'fixture:workflow',
                versionRef: 'fixture:version1', scalarSnapshot: JsonDocuments::capture(['marker' => 'provider-effect']), requestKey: 'provider-workflow',
            ), $writer);
        });
        $proof->same(CaptureStatus::Created, $capture->status, 'Real Workflow capture writes a sealed intent on the caller transaction');
        $proof->check(str_contains(runCommand($proof, 'corex:messaging:relay', ['--once' => true, '--batch' => 1]), 'published 1'), 'Core relay commits a real webhook fanout from a sealed fact');

        return [
            'notifications' => $database->table('ntf_deliveries')->where('notification_id', $notification->id)->firstOrFail()->id,
            'webhooks' => $database->table('sys_api_webhook_deliveries')->where('event_id', $message->messageId)->firstOrFail()->id,
            'workflow' => $capture->intentRef,
        ];
    });
    $proof->same(1, $control->table('scenario_broker_receipts')->count(), 'The independent broker records the one original fact');
    $proof->same(1, $database->table('sys_inbox_messages')->count(), 'Installed Webhook fanout creates its own durable inbox receipt');
    foreach ($refs as $owner => $id) {
        $proof->check(is_string($id) && $id !== '', 'Each installed owner produced a durable row');
        $proof->same([], ProviderSimulator::evidence($owner)['requests'], 'The owner producer leaves future sends to its worker');
    }
    Scenario::barrier('provider-intents', ['refs' => $refs]);
    $proof->report($database, array_values($refs), [], ['migrations' => $migrations, 'intents' => $refs]);

    return;
}
$refs = Scenario::barrierRead('provider-intents')['refs'];
$notifications = static fn () => ownerRow($database, 'notifications', $refs['notifications']);
$workflow = static fn () => ownerRow($database, 'workflow', $refs['workflow']);
$webhook = static fn () => ownerRow($database, 'webhooks', $refs['webhooks']);

switch ($mode) {
    case 'notification-send':
        inWorkspace(static fn () => app(DeliveryExecution::class)->deliver($refs['notifications']));

        throw new RuntimeException('The runner must kill the worker at the lost ACK barrier.');
    case 'notification-sending':
        $row = $notifications();
        $ticket = providerTicket($control, $row);
        $proof->same('sending', $row->state, 'Independent SQL observes sending after the provider committed');
        $proof->same('outstanding', $ticket->state, 'The provider ticket was registered before I/O');
        $proof->same($row->claim_token, $ticket->claim_token, 'The committed owner row and independent ticket match the exact sending claim');
        $proof->same($row->delivery_key, $ticket->effect_key, 'The separate control store contains the same immutable key');
        $journal = singleEffect($proof, 'notifications', $row->delivery_key);
        Scenario::barrier('notification-sending', ['ticket' => $row->provider_ticket, 'lease' => $row->lease_until, 'key' => $row->delivery_key]);
        $proof->report($database, [$row->id, $row->provider_ticket], [], ['state' => $row->state, 'journal' => $journal]);

        break;

    case 'notification-expired':
        $prior = Scenario::barrierRead('notification-sending');
        $clock->advance(10);
        $proof->check($clock->now() > new DateTimeImmutable($prior['lease']), 'The recovery clock passed the persisted sending lease');
        $output = inWorkspace(static fn () => runCommand($proof, 'corex:notifications:sweep', ['--limit' => 1]));
        $row = $notifications();
        $proof->same('unknown', $row->state, 'The new worker converts the expired send to unknown');
        $proof->same($prior['ticket'], $row->provider_ticket, 'Recovery keeps the original provider ticket');
        $proof->same(null, $row->provider_ref, 'Lost ACK left no local completion');
        $journal = singleEffect($proof, 'notifications', $row->delivery_key);
        $proof->report($database, [$row->id], ['state' => 'sending'], ['state' => $row->state, 'command' => $output, 'journal' => $journal]);

        break;

    case 'notification-hidden-lookup':
    case 'notification-confirmed':
        $positive = $mode === 'notification-confirmed';
        $clock->advance($positive ? 50 : 10);
        inWorkspace(static function () use ($refs, $positive): void {
            $owner = app(DeliveryExecution::class);
            $owner->reconcile($refs['notifications']);

            if ($positive) {
                $owner->reconcile($refs['notifications']);
            }
        });
        $row = $notifications();
        $journal = singleEffect($proof, 'notifications', $row->delivery_key);
        $states = array_column($journal['lookups'], 'result');

        if ($positive) {
            $proof->check(in_array('confirmed', $states, true), 'The owner performs a positive lookup on the original provider key');
            $proof->same('completed', $row->state, 'A positive lookup commits the owner completion');
            $proof->same($journal['effects'][0]['provider_ref'], $row->provider_ref, 'Owner completion keeps the actual provider reference');
            $proof->same('reconciled', providerTicket($control, $row)->state, 'The independent ticket is reconciled after the owner commit');
            $proof->same(1, $database->table('ntf_deliveries')->where('delivery_key', $row->delivery_key)->where('state', 'completed')->count(), 'There is one completed owner row for the effect');
        } else {
            $proof->check(in_array('missing', $states, true), 'An eventually consistent lookup returned not found before visibility');
            $proof->same('unknown', $row->state, 'An early negative lookup preserves unknown');
            $proof->same(null, $row->provider_ref, 'Early negative lookup yields no local completion');
        }
        $proof->report($database, [$row->id, $row->provider_ticket], [], ['state' => $row->state, 'provider_ref' => $row->provider_ref, 'journal' => $journal]);

        break;

    case 'workflow-send':
        $output = inWorkspace(static fn () => runCommand($proof, 'corex:workflow:sweep', ['--limit' => 1]));
        $row = $workflow();
        $proof->same('unknown', $row->state, 'The old positive ACK cannot complete its expired owner token');
        $proof->report($database, [$row->id, $row->provider_ticket], ['state' => 'sending'], ['state' => $row->state, 'command' => $output]);

        break;

    case 'workflow-expired':
        $before = $workflow();
        $proof->same('sending', $before->state, 'The real engine start commits sending before its response');
        $proof->same($before->claim_token, providerTicket($control, $before)->claim_token, 'Workflow linked the original claim token externally');
        singleEffect($proof, 'workflow', $before->effect_key);
        $clock->advance(90);
        $proof->check($clock->now() > new DateTimeImmutable($before->lease_until), 'The second workflow worker passed the original lease');
        $output = inWorkspace(static fn () => runCommand($proof, 'corex:workflow:sweep', ['--limit' => 1]));
        $after = $workflow();
        $proof->same('unknown', $after->state, 'Second worker marks the committed engine start unknown');
        $proof->same($before->provider_ticket, $after->provider_ticket, 'The old workflow provider ticket remains linked');
        Scenario::barrier('workflow-unknown', ['tuple_version' => $after->tuple_version, 'receipt' => $after->provider_receipt, 'ticket' => $after->provider_ticket]);
        $proof->report($database, [$after->id, $after->provider_ticket], ['state' => $before->state], ['state' => $after->state, 'command' => $output]);

        break;

    case 'workflow-stale':
        $checkpoint = Scenario::barrierRead('workflow-unknown');
        $row = $workflow();
        $proof->same('unknown', $row->state, 'Independent SQL still sees unknown after the late ACK');
        $proof->same($checkpoint['tuple_version'], $row->tuple_version, 'No tuple update occurred when the expired worker returned');
        $proof->same($checkpoint['receipt'], $row->provider_receipt, 'The stale ACK did not write a local receipt');
        $proof->same($checkpoint['ticket'], $row->provider_ticket, 'The stale ACK did not replace the provider ticket');
        $journal = singleEffect($proof, 'workflow', $row->effect_key);
        $ticket = providerTicket($control, $row);
        $proof->same('confirmed', $ticket->observation_kind, 'The original late ACK remains narrow external evidence');
        $proof->same($journal['effects'][0]['provider_ref'], $ticket->observation_ref, 'The external ACK carries the journal provider reference');
        $proof->report($database, [$row->id, $row->provider_ticket], $checkpoint, ['state' => $row->state, 'owner_updates' => 0, 'tuple_version' => $row->tuple_version, 'journal' => $journal]);

        break;

    case 'workflow-confirmed':
        $clock->advance(145);
        $output = inWorkspace(static fn () => runCommand($proof, 'corex:workflow:sweep', ['--limit' => 1]));
        $row = $workflow();
        $journal = singleEffect($proof, 'workflow', $row->effect_key);
        $proof->same('confirmed', $row->state, 'A fresh workflow worker projects the original confirmed effect');
        $proof->same($journal['effects'][0]['provider_ref'], $row->provider_instance, 'Workflow stores the one original engine run reference');
        $proof->same('reconciled', providerTicket($control, $row)->state, 'The workflow effect ticket is reconciled');
        $proof->report($database, [$row->id, $row->provider_ticket], [], ['state' => $row->state, 'command' => $output, 'journal' => $journal]);

        break;

    case 'webhook-send':
        $output = inWorkspace(static fn () => runCommand($proof, 'corex:webhooks:deliver', ['--max' => 1]));
        $row = $webhook();
        $journal = singleEffect($proof, 'webhooks', $row->effect_key);
        $proof->same(hash('sha256', $row->body), $journal['requests'][0]['body_sha256'], 'The provider saw the exact signed projected body');
        $proof->same('unknown', $row->status, 'An ambiguous response stays unknown on the real webhook owner');
        $proof->report($database, [$row->id, $row->provider_ticket], [], ['state' => $row->status, 'command' => $output, 'journal' => $journal]);

        break;

    case 'webhook-unknown':
        $clock->advance(60);
        $before = $webhook();
        inWorkspace(static function ($context, $scope) use ($proof, $refs): void {
            runCommand($proof, 'corex:webhooks:deliver', ['--max' => 1]);
            runCommand($proof, 'corex:webhooks:deliver', ['--max' => 1]);

            try {
                app(WebhookReplay::class)->one($scope, $refs['webhooks'], true, 'Fixture requested replay');
            } catch (ApiError $error) {
                $proof->same(409, $error->status, 'The real replay entry refuses a still unknown effect');

                return;
            }

            throw new RuntimeException('The webhook replay entry accepted an unresolved effect.');
        });
        $after = $webhook();
        $proof->same('unknown', $after->status, 'Normal polling cannot make a no-lookup effect runnable again');
        $proof->same($before->tuple_version, $after->tuple_version, 'No automatic send or replay update is permitted');
        $journal = singleEffect($proof, 'webhooks', $after->effect_key);
        $proof->same([], $journal['lookups'], 'This installed provider branch has no automatic lookup');
        $proof->report($database, [$after->id, $after->provider_ticket], ['state' => $before->status], ['state' => $after->status, 'journal' => $journal]);

        break;

    case 'webhook-operator':
        $clock->advance(100);
        $row = $webhook();
        $ticket = providerTicket($control, $row);
        $proof->same('unknown', $ticket->state, 'The independent webhook ticket is still unresolved');
        $authority = app(DatabaseScopeControl::class);
        $binding = $authority->resolve(Scenario::account(Scenario::BOXED));
        $closing = $authority->close(Scenario::BOXED, $binding->controlRevision);
        expectScopeDenial($proof, static fn () => $authority->drain(Scenario::BOXED, $closing), 'An unresolved provider ticket blocks route drain');
        $effect = new ProviderEffectRef(Scenario::BOXED, $binding->accountIncarnation, Scenario::W1, $ticket->provider_owner, $ticket->provider_namespace, $row->effect_key);
        $context = static fn (Closure $work, string $actor) => ScenarioQueue::inScope(Scenario::BOXED, Scenario::W1, $actor, $work);
        expectScopeDenial($proof, static fn () => $context(static fn () => app(ProviderEffectRecovery::class)->recover($effect), Scenario::USER_A), 'The original producer lacks closed-route operator authority');
        expectScopeDenial($proof, static fn () => $context(static fn () => app(FixtureProviderRecoveryAuthority::class)->asOperator(static fn () => app(ProviderEffectRecovery::class)->recover($effect)), Scenario::USER_B), 'An operator without the specific effect grant cannot reconcile');
        $control->table('scenario_provider_operator_grants')->insert([
            'operator_id' => Scenario::USER_B, 'account_id' => $effect->accountId, 'workspace_id' => $effect->workspaceId,
            'owner' => $effect->owner, 'provider_namespace' => $effect->providerNamespace, 'effect_key' => $effect->effectKey,
        ]);
        $journal = singleEffect($proof, 'webhooks', $row->effect_key);
        $reference = $journal['effects'][0]['provider_ref'];
        $context(static function () use ($proof, $effect, $ticket, $reference): void {
            app(FixtureProviderRecoveryAuthority::class)->asOperator(static function () use ($proof, $effect, $ticket, $reference): void {
                $ledger = app(ProviderEffectLedger::class);
                $status = $ledger->inspect($effect);
                $proof->same($ticket->ticket_id, $status?->ticket->id, 'Operator inspects the existing ticket on the independent control store');
                $ledger->observe($status->ticket, new ProviderObservation(ProviderObservationKind::Confirmed, $reference, 'operator:'.Scenario::USER_B));
                $proof->same(true, app(ProviderEffectRecovery::class)->recover($effect), 'Authorized closed-route recovery applies the independent positive evidence');
            });
        }, Scenario::USER_B);
        $after = $webhook();
        $proof->same('succeeded', $after->status, 'The original webhook owner recorded the confirmed effect');
        $proof->same($reference, $after->provider_ref, 'Webhook receipt preserves the original external provider reference');
        $proof->same('reconciled', providerTicket($control, $after)->state, 'The external ticket reconciles only after the owner receipt');
        $proof->same(1, $control->table('corex_provider_observations')->where('ticket_id', $after->provider_ticket)
            ->where('observer', 'operator:'.Scenario::USER_B)->where('reference', $reference)->count(), 'Independent positive operator evidence is durable');
        $afterJournal = singleEffect($proof, 'webhooks', $after->effect_key);
        $proof->same([], $afterJournal['lookups'], 'The no-lookup owner makes no provider calls during operator recovery');
        $obligations = $control->table('corex_scope_tickets')->where('deployment_id', Scenario::DEPLOYMENT)
            ->where('account_id', Scenario::BOXED)->whereIn('state', ['outstanding', 'unknown', 'conflicting'])
            ->orderBy('ticket_id')->get(['ticket_id', 'kind', 'state'])->all();
        $proof->check($obligations !== [], 'Owner process death leaves a separate local admission obligation');
        foreach ($obligations as $obligation) {
            $proof->same(AdmissionKind::LocalWrite->value, $obligation->kind, 'All provider tickets are reconciled before deciding route readiness');
        }
        expectScopeDenial($proof, static fn () => $authority->drain(Scenario::BOXED, $closing), 'Provider reconciliation alone cannot clear an unverified local transaction');
        $proof->report($database, [$after->id, $after->provider_ticket], ['state' => $row->status], [
            'state' => $after->status, 'operator' => Scenario::USER_B, 'provider_ref' => $after->provider_ref,
            'journal' => $afterJournal, 'local_obligations' => $obligations,
        ]);

        break;

    default:
        throw new RuntimeException('Unknown provider outcome scenario action.');
}
