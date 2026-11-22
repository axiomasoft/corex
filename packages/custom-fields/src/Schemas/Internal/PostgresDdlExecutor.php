<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Illuminate\Database\Connection;
use Packman\Attributes\Internal;
use stdClass;

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
        DirectDdlSession::withRelationLock(route: $this->route, db: $db, scope: $scope, spec: $spec, work: function (DirectDdlSession $session) use ($db, $scope, $claim, $spec, $retire, $ack): void {
            $entity = new EntityHandle(SchemaValue::text($claim->entity));
            $db->transaction(function () use ($db, $scope, $claim, $spec, $retire, $session, $entity): void {
                $head = $this->heads->row(db: $db, scope: $scope, entity: $entity, exclusive: true, allowManifestChange: true);

                if ($head->pending_plan !== $claim->id) {
                    throw new SchemaClaimLost('Head ownership changed.');
                }
                $row = $this->journal->locked(db: $db, scope: $scope, claim: $claim);
                $object = IndexCatalogue::inspect(db: $db, spec: $spec);
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
                $intent['backend'] = $session->backend;
                SchemaRows::one($this->journal->claim(db: $db, scope: $scope, claim: $claim)->update(['ddl_intent' => SchemaRows::json($intent)]));
            });
            $session->assertOwner();
            $object = IndexCatalogue::inspect(db: $db, spec: $spec);
            $qualified = IndexCatalogue::identifier(SchemaValue::text($spec['namespace'])).'.';
            $name = IndexCatalogue::identifier(SchemaValue::text($spec['name']));

            if ($object !== null && ($retire !== null || ! $object->indisvalid || ! $object->indisready || ! $object->indislive)) {
                $session->assertOwner();
                $this->guardDispatch(db: $db, scope: $scope, claim: $claim);
                $db->statement('DROP INDEX CONCURRENTLY '.$qualified.$name);
                $session->assertOwner();
                $object = IndexCatalogue::inspect(db: $db, spec: $spec);

                if ($object !== null) {
                    throw new SchemaConflict('index-drop-not-confirmed');
                }
            }

            if ($retire === null && $object === null) {
                $session->assertOwner();
                $this->guardDispatch(db: $db, scope: $scope, claim: $claim);
                $parent = $qualified.IndexCatalogue::identifier(SchemaValue::text($spec['parent_name']));
                $db->statement('CREATE INDEX CONCURRENTLY '.$name.' ON '.$parent.' USING btree (('.SchemaValue::text($spec['expression']).'), record_id) WHERE '.SchemaValue::text($spec['predicate']));
            }
            $db->transaction(function () use ($db, $scope, $claim, $spec, $retire, $ack, $session, $entity): void {
                $session->assertOwner();
                $head = $this->heads->row(db: $db, scope: $scope, entity: $entity, exclusive: true, allowManifestChange: true);

                if ($head->pending_plan !== $claim->id) {
                    throw new SchemaClaimLost('Head ownership changed before DDL acknowledgement.');
                }
                $actual = IndexCatalogue::inspect(db: $db, spec: $spec);

                if (($retire === null && ($actual === null || ! $actual->indisready || ! $actual->indisvalid || ! $actual->indislive))
                    || ($retire !== null && $actual !== null)) {
                    throw new SchemaConflict('index-acknowledgement-mismatch');
                }
                $ack($actual === null ? null : ['digest' => $spec['digest'], 'parent_oid' => $spec['parent_oid'], 'namespace_oid' => $spec['namespace_oid'],
                    'oid' => $actual->oid, 'filenode' => $actual->filenode, 'definition' => $actual->definition, 'indisvalid' => true, 'indisready' => true]);
            });
        });
    }

    private function guardDispatch(Connection $db, DataScope $scope, stdClass $claim): void
    {
        if (! $this->journal->claim(db: $db, scope: $scope, claim: $claim)->exists()) {
            throw new SchemaClaimLost('DDL claim expired before dispatch.');
        }
    }
}
