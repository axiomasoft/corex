<?php

declare(strict_types=1);

// The parent performs schedule() in a live Laravel transaction. Keep the competing
// insert uncommitted until PostgreSQL itself reports that it blocks that backend.
[$connection, $row, $parentPid] = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
$db = new PDO($dsn, $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->beginTransaction();
$columns = implode(', ', array_keys($row));
$bindings = implode(', ', array_fill(0, count($row), '?'));
$db->prepare("insert into sys_wakeup_requests ($columns) values ($bindings)")->execute(array_values($row));
$pid = $db->query('select pg_backend_pid()')->fetchColumn();
fwrite(STDOUT, $pid.PHP_EOL);
fflush(STDOUT);
$barrier = $db->prepare('select pg_backend_pid() = any(pg_blocking_pids(?))');
$end = microtime(true) + 10;
while (microtime(true) < $end) {
    $barrier->execute([$parentPid]);

    if ($barrier->fetchColumn()) {
        $db->commit();
        exit(0);
    }
    time_nanosleep(0, 5_000_000);
}

throw new RuntimeException('The competing schedule never reached its PostgreSQL lock.');
