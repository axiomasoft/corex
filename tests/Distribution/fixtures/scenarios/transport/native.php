<?php

declare(strict_types=1);

use App\Scenario\Scenario;
use App\TransportProof\CarrierSnapshot;
use App\TransportProof\Wire;
use Illuminate\Database\Connection;

require getcwd().'/vendor/autoload.php';
require __DIR__.'/setup.php';
Scenario::boot();
$proof = new Scenario;
$db = Scenario::connection('a');
$mode = $argv[1] ?? throw new LogicException('Missing native transport phase.');
$connections = [];
foreach (['control', 'a', 'b', 'budget'] as $role) {
    $connection = Scenario::connection($role);
    $connections[$role] = [...Scenario::describe($connection), 'version_num' => (int) $connection->scalar('SHOW server_version_num')];
}
$before = compact('connections');
$after = ['phase' => $mode, 'php' => PHP_VERSION, 'connections' => $connections];
$refs = [];
$input = ['resource_id' => TRANSPORT_RECORD, 'title' => 'equipment'];
$tool = 'notes_create__v2';
$path = '/api/v2/notes';

switch ($mode) {
    case 'setup':
        $after['setup'] = setupTransport($proof, $db, true);
        $proof->same([], ledger($db)['effects'], 'Transport starts without business effects.');
        $proof->same([], ledger($db)['budget'], 'Transport starts without paid requests.');

        break;

    case 'http-first':
    case 'http-replay':
        $before['ledger'] = ledger($db);
        $key = $mode === 'http-first' ? 'transport-http-first' : 'transport-mcp-first';
        $response = Wire::http('POST', $path, $input, ['key' => $key, 'headers' => ['HTTP_X_COREX_CLIENT_ID' => 'forged-client']]);
        $proof->same(201, $response['status'], 'Real v2 Laravel dispatch succeeds.');
        $proof->same('alpha-client', $response['identity']['client'], 'A user-supplied client header cannot choose the trusted identity.');
        $proof->same($mode === 'http-replay', $response['body']['meta']['replayed'], 'HTTP projects the persisted replay status.');
        $proof->same(TRANSPORT_SECRET, $response['body']['data']['private_label'], 'The admitted output field is visible before revoke.');
        $after += ['response' => $response, 'ledger' => ledger($db)];
        $refs = ['note' => $response['body']['data']['id'], 'key' => $key];

        break;

    case 'observe-open':
        $barrier = Scenario::barrierRead('notes-outer-open');
        $state = ledger($db);
        $proof->same(1, $barrier['transaction_level'], 'Middleware caller still owns the outer transaction.');
        $proof->same(201, $barrier['status'], 'The handler returned before externally visible tenant commit.');
        $proof->check($barrier['connection']['session'] !== $connections['a']['session'], 'Independent PostgreSQL observer uses a different session.');
        $proof->same([], $state['effects'], 'Open transaction hides the already returned business effect.');
        $proof->same([], $state['receipts'], 'Open transaction hides the final tenant receipt.');
        $proof->same(['held'], array_column($state['budget'], 'state'), 'Dedicated independent reservation is already held.');
        $after += ['barrier' => $barrier, 'ledger' => $state];

        break;

    case 'mcp-replay':
    case 'mcp-first':
        $before['ledger'] = ledger($db);
        $initialize = Wire::rpc('initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => (object) []]);
        $notify = Wire::rpc('notifications/initialized');
        $listed = Wire::rpc('tools/list', id: 9);
        $entry = array_find($listed['body']['result']['tools'], static fn (array $item): bool => $item['name'] === $tool);
        $proof->same('2025-11-25', $initialize['body']['result']['protocolVersion'], 'Actual MCP initializes using the public protocol.');
        $proof->same([202, 0], [$notify['status'], $notify['size']], 'Actual initialization notification has no body.');
        $proof->same('v2:notes.create', $entry['operationRef'], 'The tool resolves the installed compiled operation.');
        $proof->check($entry['contractHash'] !== $entry['operationContractHash'], 'Tool and operation identities remain separate.');
        $key = $mode === 'mcp-replay' ? 'transport-http-first' : 'transport-mcp-first';
        $response = Wire::call($tool, array_reverse($input, true), $key, id: 22);
        $proof->same([200, false, 22], [$response['status'], $response['body']['result']['isError'], $response['body']['id']], 'The actual replay is a successful JSON-RPC tool result.');
        $content = $response['body']['result']['structuredContent'];
        $proof->same(TRANSPORT_SECRET, $content['private_label'], 'The real tool returns the current allowed output.');
        $after += ['initialize' => $initialize, 'notification' => $notify, 'list' => $listed, 'response' => $response, 'ledger' => ledger($db)];
        $refs = ['note' => $content['id'], 'key' => $key];

        break;

    case 'conflicts':
        $before['ledger'] = ledger($db);
        $changed = [...$input, 'title' => 'semantic mismatch'];
        $conflict = Wire::http('POST', $path, $changed, ['key' => 'transport-http-first']);
        $mcpConflict = Wire::call($tool, $changed, 'transport-http-first');
        $proof->same([409, 'operations.idempotency_conflict'], [$conflict['status'], $conflict['body']['code']], 'HTTP uses the owner key conflict.');
        $proof->same($conflict['body']['code'], $mcpConflict['body']['result']['structuredContent']['code'], 'Tool invocation carries the same registered conflict.');
        $invalid = [...$input, 'title' => str_repeat('z', 81)];
        $http = Wire::http('POST', $path, $invalid, ['key' => 'bad-title']);
        $mcp = Wire::call($tool, $invalid, 'bad-title');
        $problem = $mcp['body']['result']['structuredContent'];
        $proof->same([422, 'application/problem+json', '/title'], [$http['status'], $http['media_type'], $http['body']['path']], 'HTTP projects a safe RFC 9457 field problem.');
        foreach (['code', 'category', 'retryable', 'path'] as $field) {
            $proof->same($http['body'][$field], $problem[$field], 'HTTP and MCP project the same field problem '.$field);
        }
        $badEnvelope = Wire::rpc('tools/call', ['name' => $tool, 'arguments' => (object) $input, 'clientId' => 'foreign']);
        $proof->same(-32602, $badEnvelope['body']['error']['code'], 'Protocol refuses client identity injection.');
        $foreign = [];
        foreach (['transport-beta', 'transport-workspace', 'transport-account'] as $token) {
            $response = Wire::http('POST', $path, $input, ['key' => 'transport-http-first', 'token' => $token]);
            $proof->check(in_array($response['status'], [403, 404], true), 'Foreign identity cannot recover the persisted ref.');
            $proof->check(! str_contains(json_encode($response['body'], JSON_THROW_ON_ERROR), TRANSPORT_SECRET), 'No stored output leaks.');
            $foreign[$token] = $response;
        }
        $proof->same($before['ledger'], ledger($db), 'Mismatches, malformed input and foreign identities create no receipts, effects or budget charges.');
        $after += ['conflict' => $conflict, 'mcp_conflict' => $mcpConflict, 'invalid' => $http, 'mcp_invalid' => $mcp, 'forged' => $badEnvelope, 'foreign' => $foreign, 'ledger' => ledger($db)];

        break;

    case 'output-revoke':
        $before['ledger'] = ledger($db);
        removeGrant($db, 'read-private-label');
        $after += ['revoked' => 'read-private-label', 'ledger' => ledger($db)];
        $proof->same($before['ledger'], $after['ledger'], 'Changing only output permission leaves the ledger unchanged.');

        break;

    case 'output-read':
        $before['ledger'] = ledger($db);
        $http = Wire::http('POST', $path, $input, ['key' => 'transport-http-first']);
        $mcp = Wire::call($tool, $input, 'transport-http-first');
        $proof->same(201, $http['status'], 'Action remains permitted after output-only revoke.');
        $proof->same($http['body']['data'], $mcp['body']['result']['structuredContent'], 'Both transports reproject the same current field view.');
        $proof->check(! array_key_exists('private_label', $http['body']['data']), 'Previously visible field is pruned.');
        $proof->same($before['ledger'], ledger($db), 'Output projection does not create another effect or charge.');
        $after += ['http' => $http, 'mcp' => $mcp, 'ledger' => ledger($db)];

        break;

    case 'denials':
        $before['ledger'] = ledger($db);
        foreach (['notes.write', 'record:'.TRANSPORT_RECORD, 'client', 'write-title'] as $grant) {
            removeGrant($db, $grant);
            $http = Wire::http('POST', $path, $input, ['key' => 'transport-http-first']);
            $mcp = Wire::call($tool, $input, 'transport-http-first');
            $list = Wire::rpc('tools/list');
            $proof->check(in_array($http['status'], [403, 404], true), 'No HTTP replay is allowed after policy revoke.');
            $proof->check(isset($mcp['body']['error']) || $mcp['body']['result']['isError'] === true, 'MCP denies the same replay.');
            $proof->check(! str_contains(json_encode([$http['body'], $mcp['body']], JSON_THROW_ON_ERROR), TRANSPORT_SECRET), 'Denied channels do not release saved data.');

            if (in_array($grant, ['notes.write', 'client'], true)) {
                $proof->same([], $list['body']['result']['tools'], 'Current tool listing filters revoked admission.');
            }
            $after['revocations'][$grant] = ['removed' => $grant, 'http' => $http, 'mcp' => $mcp, 'list' => $list];
            grantTransport($db, Scenario::W1, Scenario::USER_A, 'alpha-client', [$grant]);
        }
        $proof->same($before['ledger'], ledger($db), 'All denied replays leave the complete tenant and dedicated ledgers unchanged.');
        $after['ledger'] = ledger($db);

        break;

    case 'carriers':
        $after['carrier'] = CarrierSnapshot::capture($proof, 'canonical-carriers');

        break;

    case 'native-versions':
        $before['ledger'] = ledger($db);
        $positive = Wire::http('POST', $path, $input, ['key' => 'transport-http-first']);
        $proof->same(201, $positive['status'], 'Native v2 positive uses the real installed route.');
        foreach (['v1', 'v3'] as $major) {
            $negative = Wire::http('POST', '/api/'.$major.'/notes', $input, ['key' => 'transport-http-first']);
            $proof->same(404, $negative['status'], 'Concrete operation does not mount unsupported '.$major.'.');
            $proof->same(null, $negative['route'], 'The unsupported major did not select a Laravel route.');
            $after['versions'][$major] = $negative;
        }
        $missing = Wire::http('GET', '/api/v2/entities/eq.equipment/fields');
        $proof->same([503, 'operations.capability_unavailable'], [$missing['status'], $missing['body']['code']], 'Plain installed Operations refuses an unbound metadata owner.');
        $after += ['positive' => $positive, 'missing_metadata' => $missing, 'ledger' => ledger($db)];

        break;

    default:
        throw new LogicException('Unknown native transport phase '.$mode);
}
$proof->report($db, $refs, $before, $after);

function ledger(Connection $db): array
{
    $budget = Scenario::connection('budget');

    return [
        'effects' => $db->table('installed_notes')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
        'receipts' => $db->table('sys_api_idempotency')->where('operation_id', 'notes.create')->oldest()->orderBy('key_hash')->get()->map(static fn (object $row): array => (array) $row)->all(),
        'budget' => $budget->table('sys_api_budget_reservations')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
        'buckets' => $budget->table('sys_api_budget_buckets')->orderBy('key')->get()->map(static fn (object $row): array => (array) $row)->all(),
    ];
}

function removeGrant(Connection $db, string $grant): void
{
    if ($db->table('installed_transport_grants')->where(['workspace_id' => Scenario::W1, 'client_id' => 'alpha-client', 'grant' => $grant])->delete() !== 1) {
        throw new RuntimeException('Expected one live grant for '.$grant);
    }
}
