<?php

declare(strict_types=1);

// Separate observer process and two independent sockets. Only stop a backend building the asserted index
// in this disposable database, after PostgreSQL identifies our own held snapshot as its blocker.
[$script,$directory,$parent,$name] = $argv;
$database = getenv('PGSQL_DATABASE');

if (! is_string($database) || ! str_ends_with($database, '_test')) {
    throw new RuntimeException('The CIC observer requires a disposable test database.');
}
$dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', getenv('PGSQL_HOST') ?: '127.0.0.1', getenv('PGSQL_PORT'), $database);
$connection = static fn (): PDO => new PDO($dsn, getenv('PGSQL_USERNAME') ?: 'corex', getenv('PGSQL_PASSWORD') ?: 'corex', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$blocker = $connection();
$observer = $connection();
$blocker->exec('BEGIN ISOLATION LEVEL REPEATABLE READ');
$record = $blocker->query("SELECT current_database() AS db,pg_backend_pid() AS pid,'public.sys_attribute_documents'::regclass::oid AS parent")->fetch(PDO::FETCH_ASSOC);

if ($record['db'] !== $database || (string) $record['parent'] !== $parent) {
    throw new RuntimeException('Observer route or parent mismatch.');
}
file_put_contents($directory.'/ready', json_encode($record, JSON_THROW_ON_ERROR));
$query = $observer->prepare(<<<'SQL'
    SELECT p.pid, p.current_locker_pid, p.index_relid::text AS oid, p.phase, a.backend_start::text AS backend_start,
           pg_backend_pid() AS observer_pid
    FROM pg_stat_progress_create_index p JOIN pg_stat_activity a USING(pid) JOIN pg_class x ON x.oid=p.index_relid
    WHERE p.datname=current_database() AND a.usename=current_user AND p.relid=CAST(? AS oid)
      AND x.relname=? AND x.relnamespace='public'::regnamespace AND p.current_locker_pid=?
      AND p.phase='waiting for old snapshots' AND EXISTS (SELECT FROM pg_locks l WHERE l.locktype='advisory' AND l.pid=p.pid AND l.granted)
    SQL);
$until = hrtime(true) + 15_000_000_000;
do {
    $query->execute([$parent, $name, $record['pid']]);
    $work = $query->fetch(PDO::FETCH_ASSOC);

    if ($work !== false) {
        $stop = $observer->prepare('SELECT pg_terminate_backend(pid,5000) FROM pg_stat_activity WHERE pid=? AND backend_start=? AND datname=current_database() AND usename=current_user');
        $stop->execute([$work['pid'], $work['backend_start']]);

        if ($stop->fetchColumn() !== true) {
            throw new RuntimeException('Observed CIC worker did not stop.');
        }
        $invalid = $observer->prepare('SELECT indisvalid,indisready,indrelid::text AS parent FROM pg_index WHERE indexrelid=?::oid');
        $invalid->execute([$work['oid']]);
        $state = $invalid->fetch(PDO::FETCH_ASSOC);

        if (! is_array($state) || $state['indisvalid'] !== false || $state['parent'] !== $parent) {
            throw new RuntimeException('Interrupted CIC did not leave the observed invalid index.');
        }
        $blocker->rollBack();
        file_put_contents($directory.'/result', json_encode([...$work, ...$state], JSON_THROW_ON_ERROR));
        exit(0);
    }
    usleep(15000);
} while (hrtime(true) < $until);

throw new RuntimeException('PostgreSQL never reached the required owned CIC snapshot barrier.');
