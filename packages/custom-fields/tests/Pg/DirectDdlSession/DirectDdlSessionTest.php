<?php

declare(strict_types=1);

use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Internal\DirectDdlRoute;
use CoreX\CustomFields\Schemas\Internal\DirectDdlSession;
use CoreX\CustomFields\Schemas\Internal\SchemaConflict;
use CoreX\CustomFields\Tests\Pg\SchemaPgTestCase;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Pdo\Pgsql;

uses(SchemaPgTestCase::class)->group('pg');

it('refuses a wrong scope, namespace or transport before taking the relation lock', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('height', 'int', index: 'expression_btree')));
    $row = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
    $spec = json_decode($row->plan, true, flags: JSON_THROW_ON_ERROR)['ddl'];
    $route = $this->app->make(DirectDdlRoute::class);
    $scope = $this->scope;
    $attempt = static fn ($scope) => DirectDdlSession::withRelationLock($route, DB::connection(), $scope, $spec, static fn () => throw new LogicException('Refused route reached the owner callback.'));
    $locks = [];
    DB::listen(function (QueryExecuted $event) use (&$locks): void {
        if (str_contains($event->sql, 'pg_try_advisory_lock') || str_contains($event->sql, 'CONCURRENTLY')) {
            $locks[] = $event->sql;
        }
    });
    foreach ([
        'accountId' => 'other-account', 'workspaceId' => 'other-workspace',
        'accountIncarnation' => 'other-incarnation', 'deploymentId' => 'other-deployment',
        'restoreEpoch' => str_repeat('d', 64), 'bindingId' => 'other-binding',
        'bindingGeneration' => $scope->bindingGeneration + 1,
    ] as $property => $wrong) {
        $other = new DataScope(...[...get_object_vars($scope), $property => $wrong]);
        expect(fn () => $attempt($other))->toThrow(ScopeContextMismatch::class);
    }
    $db = DB::connection();
    $db->statement('SET search_path TO pg_catalog');
    expect(fn () => $attempt($scope))->toThrow(SchemaConflict::class, 'direct-ddl-physical-owner-mismatch');
    $db->statement('SET search_path TO public');

    // Exercise Laravel's actual factory: read/write configuration is flattened and owns a separate read PDO.
    $config = config('database.connections.pgsql');
    foreach ([
        ['read' => ['host' => $config['host']], 'write' => ['host' => $config['host']]],
        ['direct' => ['host' => $config['host']]],
        ['url' => sprintf('postgresql://%s:%d/%s', $config['host'], $config['port'], $config['database'])],
        ['connect_via_port' => $config['port']],
        ['connect_via_database' => $config['database']],
        ['options' => [PDO::ATTR_PERSISTENT => true]],
    ] as $override) {
        config(['database.connections.pgsql' => array_replace($config, $override)]);
        DB::purge('pgsql');
        expect(fn () => $attempt($scope))->toThrow(SchemaConflict::class, 'direct-ddl-transport-override');
    }
    config(['database.connections.pgsql' => $config]);
    DB::purge('pgsql');
    expect($locks)->toBe([])->and(DB::scalar('SELECT to_regclass(?)', ['public.'.$spec['name']]))->toBeNull();
});

it('keeps the exact relation lock and native writer through CIC and committed acknowledgement', function (string $stop): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('width', 'int', index: 'expression_btree')));
    $this->step();
    $db = DB::connection();
    config(['database.connections.ddl_witness' => config('database.connections.pgsql')]);
    $witness = DB::connection('ddl_witness');
    $row = $witness->table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
    $spec = json_decode($row->plan, true, flags: JSON_THROW_ON_ERROR)['ddl'];
    $key = 'corex.fields.relation/'.$spec['database_oid'].'/'.$spec['parent_oid'];
    $writer = $db->getPdo();
    expect($writer)->toBeInstanceOf(Pgsql::class);
    $pid = $writer->getPid();
    $start = $db->scalar('SELECT backend_start::text FROM pg_catalog.pg_stat_activity WHERE pid=pg_backend_pid()');
    $observesLock = static fn () => $witness->scalar(<<<'SQL'
        SELECT EXISTS (
            SELECT FROM pg_catalog.pg_locks l JOIN pg_catalog.pg_stat_activity a USING(pid)
            WHERE a.datname=current_database() AND a.usename=current_user AND a.backend_start=?::timestamptz
              AND l.pid=? AND l.pid<>pg_backend_pid() AND l.database=?::oid AND l.granted AND l.locktype='advisory'
              AND l.classid=(pg_catalog.hashtextextended(?,0) >> 32)::int::oid
              AND l.objid=(pg_catalog.hashtextextended(?,0) & 4294967295)::oid AND l.objsubid=1)
        SQL, [$start, $pid, $spec['database_oid'], $key, $key]);
    $terminate = static function () use ($witness, $pid, $start): void {
        expect($witness->scalar(<<<'SQL'
            SELECT pg_catalog.pg_terminate_backend(pid,5000) FROM pg_catalog.pg_stat_activity
            WHERE datname=current_database() AND usename=current_user AND pid<>pg_backend_pid() AND pid=? AND backend_start=?::timestamptz
            SQL, [$pid, $start]))->toBeTrue();
    };
    $phases = [];
    $object = null;
    DB::listen(function (QueryExecuted $event) use ($db, $writer, $witness, $op, $spec, $observesLock, $terminate, $stop, &$phases, &$object): void {
        if ($event->connection !== $db) {
            return;
        }

        if (str_contains($event->sql, 'pg_try_advisory_lock')) {
            expect($db->getRawPdo())->toBe($writer)->and($observesLock())->toBeTrue();
            $phases[] = 'acquired';
        } elseif (str_starts_with($event->sql, 'CREATE INDEX CONCURRENTLY')) {
            $row = $witness->table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
            $object = $witness->selectOne('SELECT indexrelid::text AS oid, relfilenode::text AS filenode, indisvalid, indisready FROM pg_index JOIN pg_class ON oid=indexrelid WHERE indexrelid=to_regclass(?)', ['public.'.$spec['name']]);
            expect($db->getRawPdo())->toBe($writer)->and($db->transactionLevel())->toBe(0)->and($observesLock())->toBeTrue()
                ->and($row->ddl_receipt)->toBeNull()->and($row->claim_token)->toBeString()->and($object->indisvalid)->toBeTrue()->and($object->indisready)->toBeTrue();
            $phases[] = 'CIC';

            if ($stop === 'before ACK') {
                $terminate();
            }
        } elseif (str_contains($event->sql, 'pg_advisory_unlock')) {
            expect($db->getRawPdo())->toBe($writer)->and($observesLock())->toBeFalse();
            $phases[] = 'released';
        }
    });
    $db->beforeExecuting(function (string $sql) use ($db, $writer, $witness, $op, $observesLock, $terminate, $stop, $pid, $start, &$phases): void {
        if (! str_contains($sql, 'pg_advisory_unlock') || $stop === 'before ACK') {
            return;
        }
        $row = $witness->table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
        $identity = json_decode($row->ddl_intent, true, flags: JSON_THROW_ON_ERROR)['backend'];
        expect($db->getRawPdo())->toBe($writer)->and($db->transactionLevel())->toBe(0)->and($observesLock())->toBeTrue()
            ->and($row->phase)->toBe('verify')->and($row->ddl_receipt)->toBeString()
            ->and($identity['pid'])->toBe((string) $pid)->and($identity['backend_start'])->toBe($start);
        $phases[] = 'committed ACK';

        if ($stop === 'after ACK') {
            $terminate();
        }
    });

    if ($stop === 'no interruption') {
        $this->step();
        expect($phases)->toBe(['acquired', 'CIC', 'committed ACK', 'released']);
    } else {
        expect(fn () => $this->step())->toThrow(RuntimeException::class);
        expect($phases)->toBe($stop === 'before ACK' ? ['acquired', 'CIC'] : ['acquired', 'CIC', 'committed ACK'])
            ->and($db->getRawPdo())->toBeNull()->and($observesLock())->toBeFalse();
        expect($witness->scalar('SELECT count(*) FROM pg_catalog.pg_stat_activity WHERE pid=? AND backend_start=?::timestamptz', [$pid, $start]))->toBe(0);
        expect(fn () => $db->selectOne('SELECT pg_catalog.pg_backend_pid()'))->toThrow(RuntimeException::class, 'dedicated DDL session was disconnected');
    }
    $this->clock->offset += 31;
    expect($this->drain($op)->state)->toBe('completed');
    $receipt = json_decode($witness->table('sys_attribute_schema_operations')->where('id', $op->id)->value('ddl_receipt'), true, flags: JSON_THROW_ON_ERROR);
    expect($receipt['oid'])->toBe($object->oid)->and($receipt['filenode'])->toBe($object->filenode)->and($receipt['digest'])->toBe($spec['digest']);
})->with(['no interruption', 'before ACK', 'after ACK']);

it('leaves a completed index for reclamation when ownership changes before acknowledgement', function (string $fault): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('weight', 'int', index: 'expression_btree')));
    $this->step();
    $db = DB::connection();
    $pid = $db->getPdo()->getPid();
    $completed = null;
    DB::listen(function (QueryExecuted $event) use ($db, $op, $pid, $fault, &$completed): void {
        if ($event->connection !== $db || ! str_starts_with($event->sql, 'CREATE INDEX CONCURRENTLY')) {
            return;
        }
        $plan = $db->table('sys_attribute_schema_operations')->where('id', $op->id)->value('plan');
        $spec = json_decode($plan, true, flags: JSON_THROW_ON_ERROR)['ddl'];
        $completed = $db->scalar('SELECT to_regclass(?)::oid::text', ['public.'.$spec['name']]);

        if ($fault === 'live writer lost its relation lock') {
            $db->unprepared('DISCARD ALL');
            expect($db->scalar('SELECT pg_backend_pid()'))->toBe($pid);
        } else {
            expect(DB::reconnect($db->getName()))->toBe($db);
            expect($db->getPdo()->getPid())->not->toBe($pid);
        }
    });
    expect(fn () => $this->step())->toThrow(RuntimeException::class);
    expect($db->getRawPdo())->toBeNull();
    $row = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
    expect($completed)->toBeString()->and($row->phase)->toBe('index')->and($row->ddl_receipt)->toBeNull();
    expect(DB::scalar("SELECT count(*) FROM pg_catalog.pg_locks WHERE pid=? AND locktype='advisory' AND granted", [$pid]))->toBe(0);
    $this->clock->offset += 31;
    expect($this->drain($op)->state)->toBe('completed');
    $receipt = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->value('ddl_receipt');
    expect(json_decode($receipt, true, flags: JSON_THROW_ON_ERROR)['oid'])->toBe($completed);
})->with(['live writer lost its relation lock', 'manager reconnected the writer']);

it('releases the original writer and never dispatches CIC after a manager reconnect', function (string $phase): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('depth', 'int', index: 'expression_btree')));
    $this->step();
    $db = DB::connection();
    $writer = $db->getPdo();
    $reconnect = static function () use ($db, $writer): void {
        expect(DB::reconnect($db->getName()))->toBe($db);
        expect($db->getPdo()->getPid())->not->toBe($writer->getPid());
    };

    if ($phase === 'at acquisition') {
        DB::listen(static function (QueryExecuted $event) use ($db, $reconnect): void {
            if ($event->connection === $db && str_contains($event->sql, 'pg_try_advisory_lock')) {
                $reconnect();
            }
        });
    } else {
        $db->beforeExecuting(static function (string $sql) use ($reconnect): void {
            if (str_starts_with($sql, 'CREATE INDEX CONCURRENTLY')) {
                $reconnect();
            }
        });
    }
    expect(fn () => $this->step())->toThrow(RuntimeException::class);
    $row = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
    $spec = json_decode($row->plan, true, flags: JSON_THROW_ON_ERROR)['ddl'];
    expect($db->getRawPdo())->toBeNull()->and($row->ddl_receipt)->toBeNull()
        ->and(DB::scalar('SELECT to_regclass(?)', ['public.'.$spec['name']]))->toBeNull()
        ->and(DB::scalar("SELECT count(*) FROM pg_catalog.pg_locks WHERE pid=? AND locktype='advisory' AND granted", [$writer->getPid()]))->toBe(0);
    $this->clock->offset += 31;
    expect($this->drain($op)->state)->toBe('completed');
})->with(['at acquisition', 'at dispatch']);

it('disconnects the writer when PostgreSQL returns false for the relation unlock', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('length', 'int', index: 'expression_btree')));
    $db = DB::connection();
    $row = $db->table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
    $spec = json_decode($row->plan, true, flags: JSON_THROW_ON_ERROR)['ddl'];
    $route = $this->app->make(DirectDdlRoute::class);
    $work = static function () use ($db): void {
        expect($db->scalar("SELECT count(*) FROM pg_catalog.pg_locks WHERE pid=pg_backend_pid() AND locktype='advisory' AND granted"))->toBe(1);
        $db->unprepared('DISCARD ALL');
        expect($db->scalar("SELECT count(*) FROM pg_catalog.pg_locks WHERE pid=pg_backend_pid() AND locktype='advisory' AND granted"))->toBe(0);
    };
    expect(fn () => DirectDdlSession::withRelationLock($route, $db, $this->scope, $spec, $work))
        ->toThrow(RuntimeException::class, 'DDL session lock release was not confirmed.');
    expect($db->getRawPdo())->toBeNull();
    expect(fn () => $db->selectOne('SELECT pg_catalog.pg_backend_pid()'))->toThrow(RuntimeException::class, 'dedicated DDL session was disconnected');
});
