<?php

declare(strict_types=1);

use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Data\ConsumerId;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require_once __DIR__.'/../Support/Messaging.php';

beforeEach(function (): void {
    outboxRelayMigrate();
});

it('inserts exactly one inbox row when two connections race on the same key', function (): void {

    $db = DB::connection('pgsql');
    $message = messagingSampleEnvelope(['messageId' => '55555555-5555-7555-8555-555555555555']);
    $consumer = new ConsumerId('inbox.concurrency', 1);
    $db->beginTransaction();
    $inbox = resolve(Inbox::class);
    expect($inbox->claim($db, $consumer, $message))->toBeTrue();
    $row = (array) $db->table('sys_inbox_messages')->first();
    $config = $db->getConfig();
    $child = new Process([PHP_BINARY, '-r', <<<'PHP'
        [$dsn,$user,$password,$row] = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        $db = new PDO($dsn, $user, $password);
        echo $db->query('select pg_backend_pid()')->fetchColumn()."\n";
        flush();
        $columns = implode(',',array_keys($row));
        $parameters = implode(',',array_fill(0,count($row),'?'));
        $stmt=$db->prepare("insert into sys_inbox_messages ($columns) values ($parameters) on conflict (account_id,account_incarnation,workspace_scope,consumer,source,message_id) do nothing");
        $stmt->execute(array_values($row));
        echo 'inserted='.$stmt->rowCount();
        PHP], input: json_encode(["pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}", $config['username'], $config['password'], $row], JSON_THROW_ON_ERROR));
    $child->start();

    try {
        $deadline = microtime(true) + 10;
        $blocked = false;
        while (microtime(true) < $deadline && $child->isRunning()) {
            $pid = (int) trim($child->getOutput());

            if ($pid > 0 && $db->selectOne('select pg_backend_pid() = any(pg_blocking_pids(?)) as blocked', [$pid])->blocked) {
                $blocked = true;

                break;
            }
            usleep(10000);
        }
        expect($blocked)->toBeTrue();
        $db->commit();
        expect($child->wait())->toBe(0)->and($child->getOutput())->toContain('inserted=0');
        expect($db->transaction(fn () => $inbox->claim($db, new ConsumerId($consumer->name, 2), $message)))->toBeFalse()
            ->and($db->table('sys_inbox_messages')->count())->toBe(1);
    } finally {
        if ($db->transactionLevel() > 0) {
            $db->rollBack();
        }
        $child->stop();
    }
})->group('pg');
