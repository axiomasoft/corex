<?php

declare(strict_types=1);

$request = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$name = getenv('COREX_SCENARIO_DB_SINK');

if (! is_string($name) || preg_match('/\Acorex_scenario_[a-f0-9]{16}_sink_test\z/D', $name) !== 1
    || $name === getenv('COREX_SCENARIO_DB_DEFAULT') || $name === getenv('COREX_SCENARIO_DB_BUDGET')) {
    throw new RuntimeException('Expected an independently allocated PostgreSQL sink database.');
}
$db = new PDO('pgsql:host='.getenv('PGSQL_HOST').';port='.getenv('PGSQL_PORT').';dbname='.$name, getenv('PGSQL_USERNAME'), getenv('PGSQL_PASSWORD'), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$identity = $db->query("SELECT current_database() AS database, current_setting('server_version_num') AS version, pg_backend_pid() AS session")->fetch(PDO::FETCH_ASSOC);

if ($identity['database'] !== $name || (int) $identity['version'] !== (int) getenv('COREX_SCENARIO_PG_VERSION')
    || ! in_array((int) $identity['version'], [160014, 180006], true)) {
    throw new RuntimeException('The dedicated sink database identity or version differs.');
}
$scope = hash('sha256', json_encode([$request['account'], $request['incarnation'], $request['workspace']], JSON_THROW_ON_ERROR));
$fence = json_encode($request['fence'], JSON_THROW_ON_ERROR);

$db->beginTransaction();
$lock = $db->prepare('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))');
$lock->execute([$scope.':'.$request['key']]);
$lookup = $db->prepare('SELECT * FROM bulk_sink_receipts WHERE scope = ? AND effect_key = ?');
$lookup->execute([$scope, $request['key']]);
$stored = $lookup->fetch(PDO::FETCH_ASSOC);
$result = ['status' => 'absent-with-proof'];

if ($request['action'] === 'lookup' && $request['unknown']) {
    $result = ['status' => 'unknown'];
} elseif ($stored !== false) {
    $result = $stored['fence'] === $fence && hash_equals($stored['digest'], $request['digest'])
        ? ['status' => 'applied', 'receipt' => $stored['receipt'], 'record' => $stored['record_id']]
        : ['status' => 'unknown'];
} elseif ($request['action'] === 'apply') {
    $record = $request['record'];
    $receipt = hash('sha256', $scope.'|'.$request['key'].'|'.$request['digest']);
    $effect = $db->prepare('INSERT INTO bulk_sink_equipment (scope, record_id, serial) VALUES (?, ?, ?)');
    $effect->execute([$scope, $record['id'], $record['serial']]);
    $persist = $db->prepare('INSERT INTO bulk_sink_receipts (scope, effect_key, digest, fence, record_id, receipt) VALUES (?, ?, ?, ?, ?, ?)');
    $persist->execute([$scope, $request['key'], $request['digest'], $fence, $record['id'], $receipt]);
    $result = ['status' => 'applied', 'receipt' => $receipt, 'record' => $record['id']];
}
$journal = $db->prepare('INSERT INTO bulk_sink_journal (action, effect_key, outcome, provider_pid, backend_pid) VALUES (?, ?, ?, ?, ?)');
$journal->execute([$request['action'], $request['key'], $result['status'], getmypid(), $identity['session']]);
$db->commit();
echo json_encode([...$result, ...$identity, 'key' => $request['key'], 'provider_pid' => getmypid()], JSON_THROW_ON_ERROR);
