<?php

declare(strict_types=1);

use App\BulkTransfer\Runtime;
use App\Scenario\FixtureWebhookTransport;
use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use App\TransportProof\Wire;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require getcwd().'/vendor/autoload.php';
require __DIR__.'/setup.php';
Scenario::boot();
$proof = new Scenario;
$db = Runtime::tenant();
$mode = $argv[1] ?? throw new LogicException('Missing webhook phase.');
$options = ['token' => 'transport-manager'];
$subscriptions = '/api/v2/webhook-subscriptions';
$deliveries = '/api/v2/webhook-deliveries';
$before = webhookOwner($db);
$after = ['phase' => $mode, 'php' => PHP_VERSION];
$refs = [];

switch ($mode) {
    case 'create':
        $input = ['endpoint_url' => 'https://'.FixtureWebhookTransport::HOST.'/transport', 'key_refs' => [FixtureWebhookTransport::KEY]];
        $createBody = ['topic' => 'fixture.recordchanged', 'topic_version' => 1, ...$input];
        $created = Wire::http('POST', $subscriptions, $createBody, $options + ['key' => 'webhook-create']);
        $listed = Wire::http('GET', $subscriptions, options: $options);
        $proof->same([201, 200], [$created['status'], $listed['status']], 'Registered create and list use a real subscription.');
        $subscription = $created['body']['data'];
        $id = $subscription['id'];
        $proof->same($id, $listed['body']['data']['items'][0]['id'], 'Fresh operator list resolves real subscription ID.');
        $updateBody = ['expected_revision' => $subscription['revision'], ...$input];
        $updated = Wire::http('PATCH', $subscriptions.'/'.$id, $updateBody, $options + ['key' => 'webhook-update']);
        $proof->same(200, $updated['status'], 'Real subscription patch succeeds under current scope.');
        $proof->check($updated['body']['data']['revision'] > $subscription['revision'], 'Owner revision advanced after actual patch.');
        $fact = Runtime::scoped(static function (DataScope $scope): string {
            $envelope = new MessageEnvelope(
                messageId: (string) Str::uuid7(), source: 'fixture', name: 'fixture.recordchanged', contractVersion: 1,
                occurredAt: app(ScenarioClock::class)->now(), scope: $scope, payload: new MessagePayload(['record' => Scenario::RECORD]),
                piiClass: PiiClass::None, subject: new RecordRef('scenario.equipment', Scenario::RECORD), correlationId: 'installed-transport-fact',
            );
            $connection = DB::connection();
            $connection->transaction(static fn () => app(Outbox::class)->append($connection, $envelope));

            return $envelope->messageId;
        }, null);
        $proof->check(! $db->table('sys_api_webhook_deliveries')->exists(), 'The durable domain fact precedes the webhook fanout.');
        $refs = ['subscription' => $id, 'fact' => $fact];
        $after += ['create' => $created, 'list' => $listed, 'update' => $updated];
        $after['negative'] = unsupportedVersions($proof, [
            ['POST', $subscriptions, $createBody], ['GET', $subscriptions, []], ['PATCH', $subscriptions.'/'.$id, $updateBody],
        ], $options);

        break;

    case 'deliver':
        $result = Runtime::scoped(static function (): array {
            $relay = Artisan::call('corex:messaging:relay', ['--once' => true, '--batch' => 50]);
            $relayLog = Artisan::output();
            $deliver = Artisan::call('corex:webhooks:deliver', ['--max' => 1]);

            return ['relay' => $relay, 'relay_log' => $relayLog, 'deliver' => $deliver, 'deliver_log' => Artisan::output()];
        }, null);
        $state = webhookOwner($db);
        $proof->same([0, 0], [$result['relay'], $result['deliver']], 'Real relay and delivery commands ran.');
        $proof->same(['succeeded'], array_column($state['deliveries'], 'status'), 'Native claim and provider acknowledgment succeeded. '.json_encode([$result, $db->table('sys_outbox_messages')->select(['id', 'status', 'failure_class', 'last_error'])->get()->all()], JSON_THROW_ON_ERROR));
        $proof->same(1, count($state['attempts']), 'The actual attempt was stored.');
        $proof->same(1, count($state['receiver']), 'One signed delivery arrived on the isolated receiver.');
        $proof->same($state['deliveries'][0]['effect_key'], $state['receiver'][0]['effect_key'], 'The receiver saw the original durable effect key.');
        $proof->same($state['deliveries'][0]['id'], $state['receiver'][0]['delivery_ref'], 'Real signer uses the persisted delivery identity.');
        $after += ['commands' => $result, 'state' => $state];

        break;

    case 'management':
        $original = $before['deliveries'][0];
        $id = $original['id'];
        $subscription = $before['subscriptions'][0];
        $listed = Wire::http('GET', $deliveries, options: $options);
        $shown = Wire::http('GET', $deliveries.'/'.$id, options: $options);
        $attempts = Wire::http('GET', $deliveries.'/'.$id.'/attempts', options: $options);
        $proof->same([200, 200, 200], [$listed['status'], $shown['status'], $attempts['status']], 'Actual native list, show and attempts route to owner rows.');
        $proof->same($id, $shown['body']['data']['id'], 'Known ref lookup matches actual delivered identity.');
        $proof->same(1, count($attempts['body']['data']['items']), 'Actual attempt is projected.');
        $now = app(ScenarioClock::class)->now();
        $rangeBody = [
            'subscription_id' => $subscription['id'], 'from' => $now->modify('-1 hour')->format(DATE_ATOM),
            'to' => $now->modify('+1 second')->format(DATE_ATOM), 'max' => 1, 'audit_reason' => 'Installed bounded replay',
        ];
        $replayBody = ['audit_reason' => 'Installed single replay'];
        $db->table('installed_transport_grants')->where(['client_id' => 'webhook-client', 'grant' => 'webhooks.replay'])->delete();
        $denied = Wire::http('POST', $deliveries.'/'.$id.'/replay', $replayBody, $options + ['key' => 'webhook-denied']);
        $proof->same(403, $denied['status'], 'Manage permission alone does not permit replay.');
        $proof->same($before['deliveries'], webhookOwner($db)['deliveries'], 'Denied replay leaves the delivery ledger untouched.');
        grantTransport($db, null, Scenario::USER_A, 'webhook-client', ['webhooks.replay']);
        $one = Wire::http('POST', $deliveries.'/'.$id.'/replay', $replayBody, $options + ['key' => 'webhook-replay-one']);
        $range = Wire::http('POST', $deliveries.'/replay', $rangeBody, $options + ['key' => 'webhook-replay-range']);
        $proof->same([200, 200], [$one['status'], $range['status']], 'The item and literal range replay are both live.');
        $proof->check($one['route'] !== $range['route'], 'An explicit replay range is not resolved as an item resource.');
        $proof->same([[$id], 1], [$range['body']['data']['selected'], $range['body']['data']['requeued']], 'Bounded range requeues the original delivered event.');
        $beforeRetry = webhookOwner($db);
        $retry = Wire::http('POST', $deliveries.'/'.$id.'/replay', $replayBody, $options + ['key' => 'webhook-replay-one']);
        $proof->same([200, true], [$retry['status'], $retry['body']['meta']['replayed']], 'Exact retry uses the completed item replay receipt.');
        $proof->same(JsonCanonical::encode($one['body']['data']), JsonCanonical::encode($retry['body']['data']), 'Exact retry returns the original item replay data.');
        $proof->same($beforeRetry['deliveries'], webhookOwner($db)['deliveries'], 'Exact retry adds no owner work.');
        $disableBody = ['expected_revision' => $subscription['revision']];
        $disable = Wire::http('POST', $subscriptions.'/'.$subscription['id'].'/disable', $disableBody, $options + ['key' => 'webhook-disable']);
        $proof->same([200, 'disabled'], [$disable['status'], $disable['body']['data']['status']], 'Native disable changes the real subscription.');
        $state = webhookOwner($db);
        $proof->check(count($state['deliveries']) === 3 && count($state['receiver']) === 1, 'Two replays add authorized work without prematurely sending another delivery.');
        $after['negative'] = unsupportedVersions($proof, [
            ['GET', $deliveries, []], ['GET', $deliveries.'/'.$id, []], ['GET', $deliveries.'/'.$id.'/attempts', []],
            ['POST', $deliveries.'/'.$id.'/replay', $replayBody], ['POST', $deliveries.'/replay', $rangeBody],
            ['POST', $subscriptions.'/'.$subscription['id'].'/disable', $disableBody],
        ], $options);
        $postNegative = webhookOwner($db);
        $proof->same(array_diff_key($state, ['receiver_connection' => true]), array_diff_key($postNegative, ['receiver_connection' => true]), 'Unsupported versions leave real webhook rows unchanged.');
        $after += compact('listed', 'shown', 'attempts', 'denied', 'one', 'range', 'beforeRetry', 'retry', 'disable', 'state');

        break;

    default:
        throw new LogicException('Unknown webhook phase '.$mode);
}
$after['owner'] = webhookOwner($db);
$proof->report($db, $refs, $before, $after);

function webhookOwner(Connection $db): array
{
    $control = Scenario::connection('control');

    return [
        'subscriptions' => webhookRows($db, 'sys_api_webhook_subscriptions', 'id'),
        'deliveries' => webhookRows($db, 'sys_api_webhook_deliveries', 'id'),
        'attempts' => webhookRows($db, 'sys_api_webhook_attempts', 'id'),
        'receiver' => webhookRows($control, 'installed_webhook_receiver', 'effect_key'),
        'audit_jobs' => webhookRows($control, 'jobs', 'id'),
        'receiver_connection' => Scenario::describe($control),
    ];
}

function webhookRows(Connection $db, string $table, string $key): array
{
    return $db->table($table)->orderBy($key)->get()->map(static fn (object $row): array => (array) $row)->all();
}
