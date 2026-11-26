<?php

declare(strict_types=1);

use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Internal\DirectDdlRoute;
use CoreX\CustomFields\Schemas\Internal\SchemaConflict;
use CoreX\CustomFields\Schemas\Internal\SchemaWorker;
use CoreX\CustomFields\Tests\Pg\SchemaPgTestCase;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Symfony\Component\Process\Process;

uses(SchemaPgTestCase::class)->group('pg');

it('creates and verifies one owned concurrent index and reuses a fresh connection for the next phase', function (string $connection): void {
    if ($connection !== 'pgsql') {
        $connections = config('database.connections');
        config(['database.connections' => [...$connections, $connection => $connections['pgsql']]]);
        DB::setDefaultConnection($connection);
    }

    $op = $this->admit(FieldSchemaChange::add($this->field('hours', 'int', index: 'expression_btree')));
    $this->step();
    $before = DB::connection();
    $this->step();
    expect(DB::connection())->not->toBe($before);
    $end = $this->drain($op);
    expect([$end->state, $end->blockers])->toBe(['completed', []]);
    $receipt = json_decode(DB::table('sys_attribute_schema_operations')->where('id', $op->id)->value('ddl_receipt'), true, flags: JSON_THROW_ON_ERROR);
    $catalog = DB::selectOne('SELECT indrelid::text AS parent_oid,indisvalid,indisready FROM pg_index WHERE indexrelid=?::oid', [$receipt['oid']]);
    expect($receipt['parent_oid'])->toBe($catalog->parent_oid)->and($catalog->indisvalid)->toBeTrue()->and($catalog->indisready)->toBeTrue();
})->with(['pgsql', 'schema.direct']);

it('repairs the observed invalid object after another session stops the concurrent builder', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('volume', 'decimal', index: 'expression_btree')));
    $this->step();
    $stored = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
    $spec = json_decode($stored->plan, true, flags: JSON_THROW_ON_ERROR)['ddl'];
    $dir = sys_get_temp_dir().'/corex-cic-'.bin2hex(random_bytes(12));
    mkdir($dir, 0700);
    $observer = new Process([PHP_BINARY, __DIR__.'/Support/ObserveConcurrentIndex.php', $dir, $spec['parent_oid'], $spec['name']]);
    $observer->setTimeout(22)->start();

    try {
        $limit = hrtime(true) + 5_000_000_000;
        while (! is_file($dir.'/ready') && $observer->isRunning() && hrtime(true) < $limit) {
            Sleep::usleep(10000);
        }
        expect(is_file($dir.'/ready'))->toBeTrue();

        try {
            $this->step();
        } catch (Throwable) {
            // A broken socket can fail both CIC and finally-unlock. The durable intent identifies the object for B.
        }
        $observer->wait();
        expect($observer->getErrorOutput())->toBe('')->and($observer->getExitCode())->toBe(0);
        $killed = json_decode(file_get_contents($dir.'/result'), true, flags: JSON_THROW_ON_ERROR);
        expect($killed['indisvalid'])->toBeFalse()->and($killed['pid'])->not->toBe($killed['observer_pid'])
            ->and($killed['pid'])->not->toBe($killed['current_locker_pid']);
        $before = $this->schemas->inspect($this->scope, $op);
        $this->clock->offset += 31;
        $end = $this->drain($op);
        expect([$end->state, $end->blockers])->toBe(['completed', []])->and($end->generation->value)->toBeGreaterThan($before->generation->value);
        $ack = json_decode(DB::table('sys_attribute_schema_operations')->where('id', $op->id)->value('ddl_receipt'), true, flags: JSON_THROW_ON_ERROR);
        expect($ack['oid'])->not->toBe($killed['oid'])->and($ack['parent_oid'])->toBe($killed['parent'])->and($ack['indisvalid'])->toBeTrue();
    } finally {
        $observer->stop(1);
        (new Filesystem)->deleteDirectory($dir);
    }
});

it('adopts a valid index after a lost acknowledgement without rebuilding it', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('distance', 'int', index: 'expression_btree')));
    $this->step();
    $count = 0;
    DB::listen(function (QueryExecuted $query) use (&$count): void {
        if (str_starts_with($query->sql, 'CREATE INDEX CONCURRENTLY') && ++$count === 1) {
            throw new RuntimeException('fixture: successful CIC before acknowledgement');
        }
    });
    expect(fn () => $this->step())->toThrow(RuntimeException::class, 'successful CIC');
    $row = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
    $name = json_decode($row->plan, true, flags: JSON_THROW_ON_ERROR)['ddl']['name'];
    $before = DB::selectOne('SELECT c.oid::text, c.relfilenode::text FROM pg_class c JOIN pg_index i ON i.indexrelid=c.oid WHERE c.oid=to_regclass(?) AND i.indisvalid AND i.indisready', ['public.'.$name]);
    expect($row->ddl_receipt)->toBeNull()->and($before)->not->toBeNull();
    $this->clock->offset += 31;
    expect($this->drain($op)->state)->toBe('completed')->and($count)->toBe(1);
    $ack = json_decode(DB::table('sys_attribute_schema_operations')->where('id', $op->id)->value('ddl_receipt'), true, flags: JSON_THROW_ON_ERROR);
    expect($ack['oid'])->toBe($before->oid)->and($ack['filenode'])->toBe($before->relfilenode);
});

it('preserves both a wrong target and an independently fingerprinted same-name foreign relation', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('score', 'int', index: 'expression_btree')));
    $row = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
    $spec = json_decode($row->plan, true, flags: JSON_THROW_ON_ERROR)['ddl'];
    $index = $spec['name'];
    $foreign = 'field_fixture_'.bin2hex(random_bytes(7));
    DB::statement('CREATE SCHEMA '.$foreign);

    try {
        DB::statement('CREATE TABLE '.$foreign.'.sys_attribute_documents (id int PRIMARY KEY, value text)');
        DB::statement('INSERT INTO '.$foreign.".sys_attribute_documents VALUES (42,'preserve')");
        DB::statement('CREATE INDEX '.$index.' ON '.$foreign.'.sys_attribute_documents (value)');
        DB::statement('CREATE INDEX '.$index." ON public.sys_attribute_documents (record_id) WHERE entity='other.entity'");
        $fingerprint = static fn () => DB::selectOne("SELECT p.oid::text AS parent,x.oid::text AS foreign_index,x.relfilenode::text AS node,pg_get_indexdef(x.oid) AS foreign_def,pg_get_indexdef(to_regclass(?)) AS wrong_def,(SELECT encode(record_send(t),'hex') FROM ".$foreign.'.sys_attribute_documents t) AS bytes FROM pg_class p JOIN pg_index i ON i.indrelid=p.oid JOIN pg_class x ON x.oid=i.indexrelid WHERE p.oid=to_regclass(?) AND x.relname=?', ['public.'.$index, $foreign.'.sys_attribute_documents', $index]);
        $before = $fingerprint();
        $blocked = $this->drain($op);
        expect([$blocked->state, $blocked->blockers])->toBe(['blocked', ['physical-index-fingerprint-conflict']])
            ->and($fingerprint())->toEqual($before)->and($before->parent)->not->toBe($spec['parent_oid']);
    } finally {
        DB::statement('DROP SCHEMA '.$foreign.' CASCADE');
    }
});

it('closes DDL before a lock when route attestation is missing, pooled or stale', function (): void {
    $current = $this->app->make(CurrentWorkScope::class);
    $valid = $this->app->make(AccountConnectionResolver::class)->resolve(jsonbContext()->account);
    $sql = [];
    DB::listen(function (QueryExecuted $query) use (&$sql): void {
        $sql[] = $query->sql;
    });
    foreach ([[], [...$valid->options, 'corex_fields_ddl_transport' => 'transaction-pool'], [...$valid->options, 'restore_epoch' => str_repeat('b', 64)]] as $options) {
        $host = new readonly class($valid, $options) implements AccountConnectionResolver
        {
            public function __construct(private DatabaseConnectionConfig $base, private array $options) {}

            public function resolve(AccountRef $account): DatabaseConnectionConfig
            {
                return new DatabaseConnectionConfig($this->base->driver, $this->base->host, $this->base->port, $this->base->database, $this->base->username, $this->options);
            }
        };
        $route = new DirectDdlRoute($current, $host, true);
        expect(fn () => $route->namespace(DB::connection(), $this->scope))->toThrow(SchemaConflict::class);
    }
    expect(array_any($sql, static fn (string $statement): bool => str_contains($statement, 'advisory') || str_contains($statement, 'CONCURRENTLY')))->toBeFalse();
});

it('rejects a replaced parent OID under the same qualified relation name', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('sum', 'int', index: 'expression_btree')));
    $this->step();
    DB::statement('ALTER TABLE public.sys_attribute_documents RENAME TO prior_field_documents');

    try {
        DB::statement('CREATE TABLE public.sys_attribute_documents (LIKE public.prior_field_documents)');
        $new = DB::selectOne("SELECT 'public.sys_attribute_documents'::regclass::oid AS oid, 'public.prior_field_documents'::regclass::oid AS old_oid");
        expect($new->oid)->not->toBe($new->old_oid);
        $blocked = $this->drain($op);
        expect([$blocked->state, $blocked->blockers])->toBe(['blocked', ['physical-parent-replaced']])
            ->and(DB::selectOne("SELECT 'public.sys_attribute_documents'::regclass::oid AS oid")->oid)->toBe($new->oid);
    } finally {
        DB::statement('DROP TABLE IF EXISTS public.sys_attribute_documents');
        DB::statement('ALTER TABLE public.prior_field_documents RENAME TO sys_attribute_documents');
    }
});

it('keeps a live stale CIC acknowledgement from overwriting a reclaimed generation', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('speed', 'int', index: 'expression_btree')));
    $this->step();
    $primary = DB::getDefaultConnection();
    config(['database.connections.field_successor' => config('database.connections.'.$primary)]);
    $afterB = null;
    $attemptedLateAck = false;
    $firstToken = null;
    DB::listen(function (QueryExecuted $event) use ($op, $primary, &$afterB, &$attemptedLateAck, &$firstToken): void {
        if ($event->connectionName !== $primary) {
            return;
        }

        if ($afterB !== null && str_contains($event->sql, 'update "sys_attribute_schema_operations"') && str_contains($event->sql, 'ddl_receipt') && in_array($firstToken, $event->bindings, true)) {
            $attemptedLateAck = true;
        }

        if ($afterB !== null || ! str_starts_with($event->sql, 'CREATE INDEX CONCURRENTLY')) {
            return;
        }
        $first = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
        $firstToken = $first->claim_token;
        $pid = DB::selectOne('SELECT pg_backend_pid() AS pid')->pid;
        DB::setDefaultConnection('field_successor');

        try {
            expect(DB::selectOne('SELECT pg_backend_pid() AS pid')->pid)->not->toBe($pid);
            $this->clock->offset += 31;
            expect($this->app->make(SchemaWorker::class)->one())->toBeTrue();
            $afterB = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->selectRaw('generation, xmin::text AS xmin, ddl_receipt')->sole();
            expect($afterB->generation)->toBeGreaterThan($first->generation)->and($afterB->ddl_receipt)->toBeNull();
        } finally {
            DB::setDefaultConnection($primary);
        }
    });
    $this->step();
    $afterA = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->selectRaw('generation, xmin::text AS xmin, ddl_receipt')->sole();
    expect($attemptedLateAck)->toBeTrue()->and($afterA)->toEqual($afterB)
        ->and($this->drain($op)->state)->toBe('completed');
});

it('cleans an acknowledged owned index on cancellation before the effective mapping switches', function (): void {
    $op = $this->admit(FieldSchemaChange::add($this->field('before_switch', 'int', index: 'expression_btree')));
    $this->step();
    $this->step();
    $row = DB::table('sys_attribute_schema_operations')->where('id', $op->id)->sole();
    $index = json_decode($row->plan, true, flags: JSON_THROW_ON_ERROR)['ddl']['name'];
    $state = $this->schemas->inspect($this->scope, $op);
    expect($state->state)->toBe('verifying')->and(DB::selectOne('SELECT to_regclass(?) AS name', ['public.'.$index])->name)->not->toBeNull();
    $this->schemas->cancel($this->scope, $op, $state->generation);
    expect($this->drain($op)->state)->toBe('cancelled')->and(DB::selectOne('SELECT to_regclass(?) AS name', ['public.'.$index])->name)->toBeNull()
        ->and(DB::table('sys_outbox_messages')->where('name', 'fields.schema.changed')->count())->toBe(0);
});

it('uses the original index receipt before dropping a retired mapping and rejects an identically named replacement', function (): void {
    $field = $this->field('lifetime', 'int', index: 'expression_btree');
    $create = $this->admit(FieldSchemaChange::add($field));
    expect($this->drain($create)->state)->toBe('completed');
    $created = DB::table('sys_attribute_schema_operations')->where('id', $create->id)->sole();
    $index = json_decode($created->plan, true, flags: JSON_THROW_ON_ERROR)['ddl']['name'];
    $old = json_decode($created->ddl_receipt, true, flags: JSON_THROW_ON_ERROR);
    $retire = $this->admit(FieldSchemaChange::retire($this->entity, $field->id));
    $this->step();
    $this->step();
    $this->step();
    expect($this->schemas->inspect($this->scope, $retire)->state)->toBe('switched');
    DB::statement('DROP INDEX public.'.$index);
    DB::statement($old['definition']);
    $replacement = DB::selectOne('SELECT to_regclass(?)::oid::text AS oid', ['public.'.$index])->oid;
    expect($replacement)->not->toBe($old['oid']);
    $status = $this->drain($retire);
    expect([$status->state, $status->blockers])->toBe(['blocked', ['old-index-ownership-mismatch']])
        ->and(DB::selectOne('SELECT to_regclass(?)::oid::text AS oid', ['public.'.$index])->oid)->toBe($replacement);
});

it('refuses a dirty historical cast during planning before executing concurrent DDL', function (): void {
    $field = $this->field('dirty', 'int');
    expect($this->drain($this->admit(FieldSchemaChange::add($field)))->state)->toBe('completed');
    $this->writeValue($field, 'broken', 3);
    // Simulate an historical import that changed the encoded value under the original field declaration.
    $slot = DB::connection()->getPdo()->quote($field->id->value.'@1');
    DB::statement("ALTER TABLE sys_attribute_documents ALTER COLUMN slots TYPE jsonb USING jsonb_set(slots, ARRAY[$slot], to_jsonb('malformed'::text))");
    $index = $this->field('dirty', 'int', index: 'expression_btree', id: $field->id);
    expect(fn () => $this->admit(FieldSchemaChange::revise($index)))->toThrow(SchemaConflict::class, 'index-source-invalid')
        ->and(DB::table('sys_attribute_schema_operations')->count())->toBe(1);
});
