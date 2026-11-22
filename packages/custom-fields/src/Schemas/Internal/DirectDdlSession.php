<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use Closure;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
use Packman\Attributes\Internal;
use PDO;
use Pdo\Pgsql;
use RuntimeException;
use stdClass;
use Throwable;

#[Internal]
final readonly class DirectDdlSession
{
    /** @var array{pid:string,backend_start:string} */
    public array $backend;

    private Pgsql $writer;

    private string $relationKey;

    /** @param array<string,mixed> $spec */
    private function __construct(private DirectDdlRoute $route, private Connection $db, private DataScope $scope, private array $spec)
    {
        if ($db->transactionLevel() !== 0 || $route->namespace(db: $db, scope: $scope) !== $spec['namespace']) {
            throw new SchemaConflict('ddl-session-unavailable');
        }
        $writer = $db->getRawPdo();

        if (! $writer instanceof Pgsql || $writer->inTransaction() || $writer->getAttribute(PDO::ATTR_PERSISTENT) !== false) {
            throw new SchemaConflict('ddl-session-unavailable');
        }
        $this->writer = $writer;
        $this->relationKey = 'corex.fields.relation/'.SchemaValue::text($spec['database_oid']).'/'.SchemaValue::text($spec['parent_oid']);
        $db->setReconnector(static fn () => throw new RuntimeException('The dedicated DDL session was disconnected.'));
        $db->useWriteConnectionWhenReading();
        $this->backend = $this->identity(locked: false);
        IndexCatalogue::verifyParent(db: $db, spec: $spec);
    }

    /**
     * @param  array<string,mixed>  $spec
     * @param  Closure(self):void  $work
     */
    public static function withRelationLock(DirectDdlRoute $route, Connection $db, DataScope $scope, array $spec, Closure $work): void
    {
        $session = new self(route: $route, db: $db, scope: $scope, spec: $spec);
        $writer = $session->writer;
        // Manager::reconnect can replace the PDO without invoking the connection's reconnector.
        $db->beforeExecuting(self::writerGuard($writer));

        try {
            $session->runLocked($work);
        } finally {
            $writer = null;
        }
    }

    /** @return Closure(string, mixed, Connection): void */
    private static function writerGuard(?Pgsql &$writer): Closure
    {
        return static function (string $_sql, mixed $_bindings, Connection $connection) use (&$writer): void {
            if ($writer !== null && $connection->getRawPdo() !== $writer) {
                throw new RuntimeException('DDL owner session changed.');
            }
        };
    }

    /** @param Closure(self):void $work */
    private function runLocked(Closure $work): void
    {
        $bindings = [$this->relationKey, $this->backend['pid'], $this->backend['backend_start']];
        $this->db->selectOne("SELECT pg_catalog.set_config('search_path', ?, false)", [IndexCatalogue::identifier(SchemaValue::text($this->spec['namespace']))], false);

        $this->assertSession(locked: false);

        // Qualify the physical backend in the lock statement itself. No read socket or reconnect is eligible.
        try {
            $lock = $this->db->selectFromWriteConnection(<<<'SQL'
                SELECT pg_catalog.pg_try_advisory_lock(pg_catalog.hashtextextended(?,0)) AS acquired
                FROM pg_catalog.pg_stat_activity WHERE pid=pg_catalog.pg_backend_pid() AND pid=? AND backend_start=?::timestamptz
                SQL, $bindings)[0] ?? null;

            if ($this->db->getRawPdo() !== $this->writer || ! $lock instanceof stdClass || ! is_bool($lock->acquired)) {
                throw new RuntimeException('DDL session lock acquisition was not confirmed.');
            }
        } catch (Throwable $failure) {
            $this->discard($failure);
        }

        if (! $lock->acquired) {
            throw new SchemaLockBusy;
        }

        try {
            $this->assertOwner();
            $work($this);
        } finally {
            try {
                $this->release($this->db);
            } catch (Throwable $failure) {
                $this->discard($failure);
            }
        }
    }

    private function discard(Throwable $failure): never
    {
        $this->db->disconnect();
        // Finish ambiguous acquisition or release on the original native socket, even after the manager replaced it.
        $cleanup = new PostgresConnection($this->writer);
        $cleanup->setReconnector(static fn () => throw $failure);

        if ($this->writer->inTransaction()) {
            $this->writer->rollBack();
        }
        $this->release(connection: $cleanup, required: false);

        throw $failure;
    }

    private function release(Connection $connection, bool $required = true): void
    {
        if ($connection->getRawPdo() !== $this->writer) {
            throw new RuntimeException('DDL owner session changed.');
        }
        $released = $connection->selectFromWriteConnection(<<<'SQL'
            SELECT pg_catalog.pg_advisory_unlock(pg_catalog.hashtextextended(?,0)) AS released
            FROM pg_catalog.pg_stat_activity WHERE pid=pg_catalog.pg_backend_pid() AND pid=? AND backend_start=?::timestamptz
            SQL, [$this->relationKey, $this->backend['pid'], $this->backend['backend_start']])[0] ?? null;

        if ($connection->getRawPdo() !== $this->writer || ! $released instanceof stdClass || ! is_bool($released->released) || ($required && ! $released->released)) {
            throw new RuntimeException('DDL session lock release was not confirmed.');
        }
    }

    public function assertOwner(): void
    {
        $this->assertSession(locked: true);
    }

    private function assertSession(bool $locked): void
    {
        if ($this->db->getRawPdo() !== $this->writer || $this->identity(locked: $locked) !== $this->backend
            || $this->route->namespace(db: $this->db, scope: $this->scope) !== $this->spec['namespace']) {
            throw new RuntimeException('DDL owner session changed.');
        }
        IndexCatalogue::verifyParent(db: $this->db, spec: $this->spec);
    }

    /** @return array{pid:string,backend_start:string} */
    private function identity(bool $locked): array
    {
        $row = $this->db->selectOne(<<<'SQL'
            SELECT a.pid::text, a.backend_start::text, EXISTS (
                SELECT FROM pg_catalog.pg_locks l
                WHERE l.pid=a.pid AND l.database=?::oid AND l.locktype='advisory' AND l.granted
                  AND l.classid=(k.key >> 32)::int::oid AND l.objid=(k.key & 4294967295)::oid AND l.objsubid=1
                  AND (NOT ?::boolean OR l.mode='ExclusiveLock')
            ) AS held
            FROM pg_catalog.pg_stat_activity a CROSS JOIN (SELECT pg_catalog.hashtextextended(?,0) AS key) k
            WHERE a.pid=pg_catalog.pg_backend_pid() AND a.pid=?
            SQL, [$this->spec['database_oid'], $locked, $this->relationKey, $this->writer->getPid()], false);

        if (! $row instanceof stdClass || $this->db->getRawPdo() !== $this->writer) {
            throw new SchemaConflict('direct-ddl-physical-owner-mismatch');
        }

        if ($row->held !== $locked) {
            throw new RuntimeException('Unexpected DDL relation lock ownership.');
        }

        return ['pid' => SchemaValue::text($row->pid), 'backend_start' => SchemaValue::text($row->backend_start)];
    }
}
