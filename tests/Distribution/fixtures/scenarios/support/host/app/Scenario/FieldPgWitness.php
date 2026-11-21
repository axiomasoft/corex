<?php

declare(strict_types=1);

namespace App\Scenario;

use Closure;
use Illuminate\Database\Connection;
use RuntimeException;

final readonly class FieldPgWitness
{
    public const string TARGET = 'sys_attribute_documents';

    public const string FOREIGN_SCHEMA = 'scenario_foreign';

    public function __construct(private Connection $database)
    {
        $identity = Scenario::describe($database);

        if ($identity['database'] !== getenv('COREX_SCENARIO_DB_A') || preg_match('/\Acorex_scenario_[0-9a-f]+_a_test\z/D', $identity['database']) !== 1) {
            throw new RuntimeException('The observer is outside the provisioned PostgreSQL writer database.');
        }
    }

    public static function markSession(Connection $database, string $role): array
    {
        $name = 'corex-fields-'.$role.'-'.getmypid();
        $database->selectOne("SELECT set_config('application_name', ?, false)", [$name]);
        $identity = Scenario::describe($database);
        $start = $database->selectOne('SELECT backend_start::text AS started FROM pg_catalog.pg_stat_activity WHERE pid = pg_backend_pid()');

        return [...$identity, 'application' => $name, 'backend_start' => $start->started];
    }

    public function setupNeighbor(): void
    {
        $this->database->statement('CREATE SCHEMA scenario_foreign');
        $this->database->statement('CREATE TABLE scenario_foreign.sys_attribute_documents (id integer PRIMARY KEY, marker text NOT NULL)');
        $this->database->insert("INSERT INTO scenario_foreign.sys_attribute_documents VALUES (1, 'foreign-marker'), (9, 'untouched')");
    }

    public function neighborIndex(string $name): void
    {
        if (preg_match('/\A[a-z][a-z0-9_]{0,62}\z/D', $name) !== 1) {
            throw new RuntimeException('The observed plan index name is not an expected identifier.');
        }
        $this->database->statement('CREATE INDEX "'.$name.'" ON scenario_foreign.sys_attribute_documents (marker)');
    }

    public function relation(string $namespace): int
    {
        $row = $this->database->selectOne(<<<'SQL'
            SELECT c.oid::bigint AS oid
            FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON c.relnamespace = n.oid
            WHERE n.nspname = ? AND c.relname = ? AND c.relkind = 'r'
            SQL, [$namespace, self::TARGET]);

        return (int) ($row->oid ?? throw new RuntimeException('The catalog lacks the expected qualified relation.'));
    }

    public function index(string $namespace, string $name): ?array
    {
        $row = $this->database->selectOne(<<<'SQL'
            SELECT c.oid::bigint AS oid, c.relfilenode::bigint AS storage, c.relname AS name,
                x.indrelid::bigint AS parent, x.indisready AS ready, x.indisvalid AS valid, x.indislive AS live,
                pg_catalog.pg_get_indexdef(c.oid) AS definition,
                pg_catalog.pg_get_expr(x.indexprs, x.indrelid) AS expression,
                pg_catalog.pg_get_expr(x.indpred, x.indrelid) AS predicate
            FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON c.relnamespace = n.oid
            JOIN pg_catalog.pg_index x ON x.indexrelid = c.oid
            WHERE n.nspname = ? AND c.relname = ?
            SQL, [$namespace, $name]);

        return $row === null ? null : (array) $row;
    }

    public function neighborDigest(): array
    {
        $relation = $this->relation(self::FOREIGN_SCHEMA);
        $rows = $this->database->select("SELECT id, encode(convert_to(marker, 'UTF8'), 'hex') AS marker FROM scenario_foreign.sys_attribute_documents ORDER BY id");
        $catalogue = $this->database->select(<<<'SQL'
            SELECT c.oid::bigint AS oid, c.relfilenode::bigint AS storage, c.relname, pg_catalog.pg_get_indexdef(c.oid) AS definition
            FROM pg_catalog.pg_class c JOIN pg_catalog.pg_index x ON x.indexrelid = c.oid
            WHERE x.indrelid = CAST(? AS oid) ORDER BY c.relname
            SQL, [$relation]);
        $columns = $this->database->select(<<<'SQL'
            SELECT attname, atttypid::bigint AS type, attnotnull
            FROM pg_catalog.pg_attribute WHERE attrelid = CAST(? AS oid) AND attnum > 0 AND NOT attisdropped ORDER BY attnum
            SQL, [$relation]);
        $constraints = $this->database->select(<<<'SQL'
            SELECT conname, pg_catalog.pg_get_constraintdef(oid) AS definition
            FROM pg_catalog.pg_constraint WHERE conrelid = CAST(? AS oid) ORDER BY conname
            SQL, [$relation]);
        $state = ['relation' => $relation, 'rows' => $rows, 'indexes' => $catalogue, 'columns' => $columns, 'constraints' => $constraints];

        return ['sha256' => hash('sha256', json_encode($state, JSON_THROW_ON_ERROR)), 'relation' => $relation, 'indexes' => json_decode(json_encode($catalogue, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR)];
    }

    public function concurrentIndex(array $worker, int $relation, int $blocker): array
    {
        return self::await('the worker waits on the established old PostgreSQL snapshot', function () use ($worker, $relation, $blocker): ?array {
            $row = $this->database->selectOne(<<<'SQL'
                SELECT p.pid, p.relid::bigint AS parent, p.index_relid::bigint AS oid, p.phase,
                    p.current_locker_pid AS blocker, a.application_name AS application, a.backend_start::text AS backend_start,
                    EXISTS (SELECT 1 FROM pg_catalog.pg_locks l WHERE l.pid = p.pid AND l.locktype = 'advisory' AND l.granted) AS advisory
                FROM pg_catalog.pg_stat_progress_create_index p JOIN pg_catalog.pg_stat_activity a ON a.pid = p.pid
                WHERE p.datname = current_database() AND a.usename = current_user AND p.pid = ?
                    AND a.application_name = ? AND a.backend_start::text = ? AND p.relid = CAST(? AS oid)
                    AND p.phase = 'waiting for old snapshots' AND p.current_locker_pid = ?
                SQL, [$worker['session'], $worker['application'], $worker['backend_start'], $relation, $blocker]);

            return $row === null || ! $row->advisory ? null : (array) $row;
        });
    }

    public function terminateObserved(array $worker): void
    {
        // Only a session observed on this fresh database, with its original role and identity, can be signaled.
        $row = $this->database->selectOne(<<<'SQL'
            SELECT pg_catalog.pg_terminate_backend(pid, 5000) AS stopped
            FROM pg_catalog.pg_stat_activity
            WHERE datname = current_database() AND usename = current_user AND pid = ?
                AND application_name = ? AND backend_start::text = ? AND pid <> pg_backend_pid()
            SQL, [$worker['session'], $worker['application'], $worker['backend_start']]);

        if ($row !== null && ! $row->stopped) {
            throw new RuntimeException('The observed disposable worker backend did not terminate.');
        }
    }

    public function blockedBy(int $waiter, int $holder): bool
    {
        $row = $this->database->selectOne('SELECT CAST(? AS int) = ANY(pg_catalog.pg_blocking_pids(CAST(? AS int))) AS blocked', [$holder, $waiter]);

        return $row->blocked;
    }

    /**
     * @template T
     *
     * @param  Closure(): (T|null)  $check
     * @return T
     */
    public static function await(string $event, Closure $check, int $timeoutSeconds = 30, int $pollMicroseconds = 25_000): mixed
    {
        $deadline = hrtime(true) + $timeoutSeconds * 1_000_000_000;
        do {
            $value = $check();

            if ($value !== null) {
                return $value;
            }
            usleep($pollMicroseconds);
        } while (hrtime(true) < $deadline);

        throw new RuntimeException('The fixture timed out after '.$timeoutSeconds.' seconds before observing '.$event.'.');
    }
}
