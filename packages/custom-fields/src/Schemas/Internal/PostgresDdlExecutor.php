<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Illuminate\Database\Connection;
use Packman\Attributes\Internal;
use RuntimeException;
use stdClass;
use Throwable;

#[Internal]
final readonly class PostgresDdlExecutor
{
    public function __construct(private DirectDdlRoute $route, private SchemaJournal $journal, private DatabaseSchemaHeads $heads) {}

    /**
     * @param  array<string,mixed>  $spec
     * @param  array<string,mixed>|null  $retire  Original resource receipt, for contract or cancellation.
     * @param  Closure(?array<string,mixed>):void  $ack  Invoked inside the fresh owner transaction, before releasing the physical lock.
     */
    public function run(Connection $db, DataScope $scope, stdClass $claim, array $spec, ?array $retire, Closure $ack): void
    {
        if ($db->transactionLevel() !== 0 || $this->route->namespace($db, $scope) !== $spec['namespace']) {
            throw new SchemaConflict('ddl-session-unavailable');
        }
        IndexCatalogue::verifyParent($db, $spec);
        $pdo = $db->getPdo();
        $db->setReconnector(static fn () => throw new RuntimeException('The dedicated DDL session was disconnected.'));
        $db->useWriteConnectionWhenReading();
        $identity = $this->identity($db);
        $key = 'corex.fields.relation/'.SchemaValue::text($spec['database_oid']).'/'.SchemaValue::text($spec['parent_oid']);
        // Set a tenant-owned search path with implicit pg_catalog. Only the attested namespace may resolve unqualified tenant tables.
        $db->selectOne("SELECT pg_catalog.set_config('search_path', ?, false)", [IndexCatalogue::identifier(SchemaValue::text($spec['namespace']))], false);
        $lock = $db->selectOne('SELECT pg_catalog.pg_try_advisory_lock(pg_catalog.hashtextextended(?,0)) AS acquired', [$key], false);

        if (! $lock instanceof stdClass || $lock->acquired !== true) {
            throw new SchemaLockBusy;
        }

        try {
            $check = function () use ($db, $scope, $spec, $pdo, $identity): void {
                if ($db->getRawPdo() !== $pdo || $this->identity($db) !== $identity || $this->route->namespace($db, $scope) !== $spec['namespace']) {
                    throw new RuntimeException('DDL owner session changed.');
                }
                IndexCatalogue::verifyParent($db, $spec);
            };
            $check();
            $entity = new EntityHandle(SchemaValue::text($claim->entity));
            $db->transaction(function () use ($db, $scope, $claim, $spec, $retire, $identity, $entity): void {
                $head = $this->heads->row($db, $scope, $entity, true, true);

                if ($head->pending_plan !== $claim->id) {
                    throw new SchemaClaimLost('Head ownership changed.');
                }
                $row = $this->journal->locked($db, $scope, $claim);
                $object = IndexCatalogue::inspect($db, $spec);
                $intent = $row->ddl_intent === null ? [] : SchemaRows::decode($row->ddl_intent);

                if ($retire === null && $object !== null && ($intent['create_digest'] ?? null) !== $spec['digest']) {
                    throw new SchemaConflict('index-ownership-unproven');
                }
                $cancel = ($retire['cancel'] ?? false) === true && ($intent['create_digest'] ?? null) === $spec['digest'];

                if ($retire !== null && (($retire['digest'] ?? null) !== $spec['digest']
                    || ($object === null && ! $cancel && ($intent['drop_digest'] ?? null) !== $spec['digest'])
                    || ($object !== null && (! $cancel || isset($retire['oid'])) && $object->oid !== ($retire['oid'] ?? null)))) {
                    throw new SchemaConflict('old-index-ownership-mismatch');
                }
                $intent[$retire === null ? 'create_digest' : 'drop_digest'] = $spec['digest'];
                $intent['backend'] = $identity;
                SchemaRows::one($this->journal->claim($db, $scope, $claim)->update(['ddl_intent' => SchemaRows::json($intent)]));
            });
            $check();
            $object = IndexCatalogue::inspect($db, $spec);
            $qualified = IndexCatalogue::identifier(SchemaValue::text($spec['namespace'])).'.';
            $name = IndexCatalogue::identifier(SchemaValue::text($spec['name']));

            if ($object !== null && ($retire !== null || ! $object->indisvalid || ! $object->indisready || ! $object->indislive)) {
                $this->guardDispatch($db, $scope, $claim);
                $db->statement('DROP INDEX CONCURRENTLY '.$qualified.$name);
                $check();
                $object = IndexCatalogue::inspect($db, $spec);

                if ($object !== null) {
                    throw new SchemaConflict('index-drop-not-confirmed');
                }
            }

            if ($retire === null && $object === null) {
                $this->guardDispatch($db, $scope, $claim);
                $parent = $qualified.IndexCatalogue::identifier(SchemaValue::text($spec['parent_name']));
                $db->statement('CREATE INDEX CONCURRENTLY '.$name.' ON '.$parent.' USING btree (('.SchemaValue::text($spec['expression']).'), record_id) WHERE '.SchemaValue::text($spec['predicate']));
            }
            $db->transaction(function () use ($db, $scope, $claim, $spec, $retire, $ack, $check, $entity): void {
                $check();
                $head = $this->heads->row($db, $scope, $entity, true, true);

                if ($head->pending_plan !== $claim->id) {
                    throw new SchemaClaimLost('Head ownership changed before DDL acknowledgement.');
                }
                $actual = IndexCatalogue::inspect($db, $spec);

                if (($retire === null && ($actual === null || ! $actual->indisready || ! $actual->indisvalid || ! $actual->indislive))
                    || ($retire !== null && $actual !== null)) {
                    throw new SchemaConflict('index-acknowledgement-mismatch');
                }
                $ack($actual === null ? null : ['digest' => $spec['digest'], 'parent_oid' => $spec['parent_oid'], 'namespace_oid' => $spec['namespace_oid'],
                    'oid' => $actual->oid, 'filenode' => $actual->filenode, 'definition' => $actual->definition, 'indisvalid' => true, 'indisready' => true]);
            });
        } finally {
            try {
                $released = $db->selectOne('SELECT pg_catalog.pg_advisory_unlock(pg_catalog.hashtextextended(?,0)) AS released', [$key], false);

                if ($db->getRawPdo() !== $pdo || ! $released instanceof stdClass || $released->released !== true) {
                    throw new RuntimeException('DDL session lock release was not confirmed.');
                }
            } catch (Throwable $failure) {
                $db->disconnect();

                throw $failure;
            }
        }
    }

    private function guardDispatch(Connection $db, DataScope $scope, stdClass $claim): void
    {
        if (! $this->journal->claim($db, $scope, $claim)->exists()) {
            throw new SchemaClaimLost('DDL claim expired before dispatch.');
        }
    }

    /** @return array<string,string> */
    private function identity(Connection $db): array
    {
        $row = $db->selectOne('SELECT pid::text, backend_start::text FROM pg_catalog.pg_stat_activity WHERE pid=pg_backend_pid()', [], false);

        if (! $row instanceof stdClass) {
            throw new RuntimeException('Dedicated DDL backend is unavailable.');
        }

        return ['pid' => SchemaValue::text($row->pid), 'backend_start' => SchemaValue::text($row->backend_start)];
    }
}
