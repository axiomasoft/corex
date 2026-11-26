<?php

declare(strict_types=1);

use App\Scenario\FieldPgWitness;
use App\Scenario\FieldScenario;
use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Data\SchemaOperationRef;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Enums\FieldUse;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$database = Scenario::connection('a');
$catalogue = new FieldPgWitness($database);
$variant = getenv('COREX_SCENARIO_VARIANT');
$mode = $argv[1] ?? '';
const INDEX_FIELD = '01900000-0000-7000-8000-000000000041';

function indexMatches(Scenario $proof, array $actual, array $spec): void
{
    $proof->same((int) $spec['parent_oid'], (int) $actual['parent'], 'The observed index has the exact owned parent OID');
    $proof->same($spec['expression'], $actual['expression'], 'The index contains the planned expression and cast');
    $proof->same($spec['predicate'], $actual['predicate'], 'The planned logical scope and entity predicate are preserved');
}

function operationWitness(object $row): array
{
    return ['generation' => (int) $row->generation, 'claim_token' => $row->claim_token,
        'deadline' => $row->lease_until, 'tuple_version' => $row->tuple_version,
        'owner_receipt' => $row->ddl_receipt, 'phase' => $row->phase, 'state' => $row->state];
}

function headHasIndex(Connection $database, string $indexField): bool
{
    $mapping = $database->table('sys_field_schema_heads')->where('entity', FieldScenario::ENTITY)->value('mapping');

    return array_key_exists($indexField, json_decode($mapping, true, flags: JSON_THROW_ON_ERROR));
}

function advancePast(string $deadline): void
{
    $clock = app(ScenarioClock::class);
    $advance = new DateTimeImmutable($deadline)->getTimestamp() - $clock->now()->getTimestamp() + 1;

    if ($advance < 1) {
        throw new RuntimeException('Expected a previously live DDL claim lease.');
    }
    $clock->advance($advance);
}

function observeAcknowledgements(Connection $database): void
{
    $database->statement('CREATE TABLE scenario_ddl_acks (operation_id uuid NOT NULL, generation bigint NOT NULL, claim_token uuid NOT NULL, receipt jsonb NOT NULL)');
    $database->unprepared(<<<'SQL'
        CREATE FUNCTION scenario_count_ddl_ack() RETURNS trigger LANGUAGE plpgsql AS $$
        BEGIN
            INSERT INTO scenario_ddl_acks VALUES (NEW.id, OLD.generation, OLD.claim_token, NEW.ddl_receipt);
            RETURN NEW;
        END;
        $$;
        CREATE TRIGGER scenario_count_ddl_ack AFTER UPDATE OF ddl_receipt ON sys_attribute_schema_operations
        FOR EACH ROW WHEN (NEW.ddl_receipt IS NOT NULL AND OLD.ddl_receipt IS DISTINCT FROM NEW.ddl_receipt)
        EXECUTE FUNCTION scenario_count_ddl_ack();
        SQL);
}

if ($mode === 'produce') {
    $database = FieldScenario::install($proof, 'ddl-claim');
    observeAcknowledgements($database);
    $catalogue->setupNeighbor();
    FieldScenario::grant($database, Scenario::USER_A, FieldScenario::record(), [FieldUse::Read, FieldUse::Filter], [INDEX_FIELD]);
    $operation = FieldScenario::within(static function (DataScope $scope) use ($variant): SchemaOperationRef {
        $definition = new FieldDefinition(new FieldId(INDEX_FIELD), FieldScenario::entity(), 'tested_number', new FieldTypeId('int'), 'runtime',
            uses: [FieldUse::Read, FieldUse::Filter], indexPolicy: 'expression_btree');
        $operation = FieldScenario::apply($scope, FieldSchemaChange::add($definition), 'index-'.$variant);
        FieldScenario::step();

        return $operation;
    });
    $row = FieldScenario::schemaRow($database, $operation->id);
    $spec = json_decode($row->plan, true, flags: JSON_THROW_ON_ERROR)['ddl'];
    $proof->same('index', $row->phase, 'The normal worker expanded the plan and stopped at the pending index phase');
    $proof->same('public', $spec['namespace'], 'The planner used the explicit managed namespace');
    $proof->same($catalogue->relation('public'), (int) $spec['parent_oid'], 'The immutable DDL plan pins the actual physical parent');
    $proof->check($catalogue->relation(FieldPgWitness::FOREIGN_SCHEMA) !== (int) $spec['parent_oid'], 'A foreign same-name relation has a different OID');
    $catalogue->neighborIndex($spec['name']);
    $neighbor = $catalogue->neighborDigest();
    $proof->same(null, $catalogue->index('public', $spec['name']), 'No managed index exists before the installed worker runs');
    $proof->same(false, headHasIndex($database, INDEX_FIELD), 'The effective head does not yet expose the proposed field');
    Scenario::barrier('ddl-plan', ['operation' => $operation->id, 'ddl' => $spec, 'neighbor' => $neighbor, 'digest' => $row->plan_digest]);
    $proof->report($database, [$operation->id], ['target_index' => null], ['plan_digest' => $row->plan_digest, 'neighbor' => $neighbor]);

    return;
}
$plan = Scenario::barrierRead('ddl-plan');
$operation = new SchemaOperationRef($plan['operation']);
$spec = $plan['ddl'];

switch ($mode) {
    case 'snapshot':
        $database->beginTransaction();
        $database->statement("SET LOCAL transaction_isolation = 'repeatable read'");
        $database->statement('SET LOCAL transaction_read_only = on');
        $database->selectOne('SELECT EXISTS(SELECT 1 FROM public.sys_attribute_documents)');
        $identity = FieldPgWitness::markSession($database, 'old-snapshot');
        $transaction = $database->selectOne('SELECT backend_xmin::text AS xmin FROM pg_catalog.pg_stat_activity WHERE pid = pg_backend_pid()');
        $proof->check($transaction->xmin !== null, 'The independent holder has a real PostgreSQL snapshot');
        Scenario::barrier('old-snapshot', $identity);
        $database->commit();
        $proof->report($database, [$operation->id], ['snapshot' => $transaction->xmin], ['released' => true]);

        break;

    case 'worker-a':
        FieldScenario::within(static function () use ($proof, $database, $catalogue, $variant, $operation, $spec): void {
            $connection = DB::connection();
            $identity = FieldPgWitness::markSession($connection, 'ddl-a');

            if ($variant === 'interrupted') {
                $proof->same(0, $connection->transactionLevel(), 'The installed worker entered a dedicated session outside transactions');
                // The runner independently awaits PostgreSQL's CIC witness before killing this process.
                $proof->report($connection, [$operation->id], [], $identity);
            } else {
                Event::listen(QueryExecuted::class, static function (QueryExecuted $event) use ($proof, $connection, $database, $catalogue, $operation, $spec, $variant, $identity): void {
                    if ($event->connection !== $connection || ! str_starts_with($event->sql, 'CREATE INDEX CONCURRENTLY ')) {
                        return;
                    }
                    $proof->same(0, $connection->transactionLevel(), 'CIC completed outside an explicit transaction on the admitted session');
                    $proof->same($identity['session'], Scenario::describe($connection)['session'], 'CIC used the pinned worker backend');
                    $actual = $catalogue->index('public', $spec['name']);
                    indexMatches($proof, $actual, $spec);
                    $proof->same(true, $actual['valid'] && $actual['ready'] && $actual['live'], 'PostgreSQL has a committed ready index before the owner ACK');
                    $row = FieldScenario::schemaRow($database, $operation->id);
                    $proof->same(null, $row->ddl_receipt, 'The owner ACK is still absent in an independent session');
                    $proof->check(is_string($row->claim_token) && $row->claim_token !== '', 'A committed token owns this DDL attempt');
                    $checkpoint = ['index' => $actual, 'claim' => operationWitness($row)];

                    if ($variant === 'lost-ack') {
                        $proof->report($connection, [$operation->id], [], $checkpoint);
                    }
                    Scenario::barrier('ddl-before-ack', $checkpoint);
                });
            }
            Scenario::barrier('ddl-worker-enter', $identity);
            FieldScenario::step();

            if ($variant !== 'stale-ack') {
                throw new RuntimeException('The first worker must be stopped at the designated checkpoint.');
            }
            $new = Scenario::barrierRead('ddl-new-claim')['claim'];
            $row = operationWitness(FieldScenario::schemaRow($database, $operation->id));
            $proof->same($new, $row, 'The old ACK path leaves the newer committed claim and tuple unchanged');
            $proof->report($database, [$operation->id], Scenario::barrierRead('ddl-before-ack')['claim'], ['owner_updates' => 0, 'new_claim' => $row, 'ddl_session' => $identity]);
        });

        break;

    case 'progress':
        $old = Scenario::barrierRead('old-snapshot');
        $worker = Scenario::barrierRead('ddl-worker-enter');
        $progress = $catalogue->concurrentIndex($worker, (int) $spec['parent_oid'], $old['session']);
        $row = FieldScenario::schemaRow($database, $operation->id);
        $index = $catalogue->index('public', $spec['name']);
        $proof->same((int) $progress['oid'], (int) $index['oid'], 'The live CIC progress row and catalog identify the same index');
        $proof->same(false, $index['valid'], 'The index stays invalid while the old snapshot is held');
        $proof->same(false, headHasIndex($database, INDEX_FIELD), 'The invalid index is excluded from the effective field mapping');
        indexMatches($proof, $index, $spec);
        $intent = json_decode($row->ddl_intent, true, flags: JSON_THROW_ON_ERROR);
        $proof->same($spec['digest'], $intent['create_digest'], 'The create intent was committed before CIC dispatch');
        $proof->same((string) $worker['session'], $intent['backend']['pid'], 'The owner journal pins the actual PostgreSQL backend');
        $proof->same($worker['backend_start'], $intent['backend']['backend_start'], 'The journal pins backend incarnation as well as PID');
        $witness = ['progress' => $progress, 'index' => $index, 'claim' => operationWitness($row)];
        Scenario::barrier('cic-witness', $witness);
        $proof->report($database, [$operation->id], [], $witness);

        break;

    case 'stop-backend':
        $worker = Scenario::barrierRead('ddl-worker-enter');
        $recorded = Scenario::barrierRead('cic-witness');
        $proof->same($worker['session'], (int) $recorded['progress']['pid'], 'Backend termination is restricted to the proven disposable owner session');
        $catalogue->terminateObserved($worker);
        $index = $catalogue->index('public', $spec['name']);
        $proof->same(false, $index['valid'], 'The interrupted CIC leaves an invalid index behind');
        $proof->same((int) $recorded['index']['oid'], (int) $index['oid'], 'The invalid object is the same interrupted index');
        $proof->same(null, FieldScenario::schemaRow($database, $operation->id)->ddl_receipt, 'The killed attempt never acknowledges success');
        $proof->same($plan['neighbor'], $catalogue->neighborDigest(), 'The independent same-name object is unchanged after interruption');
        $proof->report($database, [$operation->id], $recorded['index'], ['invalid' => $index, 'neighbor' => $catalogue->neighborDigest()]);

        break;

    case 'successor':
        $original = Scenario::barrierRead($variant === 'interrupted' ? 'cic-witness' : 'ddl-before-ack');
        advancePast($original['claim']['deadline']);
        $ddlCount = 0;
        $new = null;
        $already = $catalogue->index('public', $spec['name']);
        FieldScenario::within(static function (DataScope $scope) use ($proof, $database, $original, $operation, $variant, &$ddlCount, &$new): void {
            $connection = DB::connection();
            FieldPgWitness::markSession($connection, 'ddl-successor');
            Event::listen(QueryExecuted::class, static function (QueryExecuted $event) use ($connection, &$ddlCount): void {
                if ($event->connection === $connection && preg_match('/\A(?:CREATE|DROP) INDEX CONCURRENTLY /', $event->sql) === 1) {
                    $ddlCount++;
                }
            });
            Event::listen(TransactionCommitted::class, static function (TransactionCommitted $event) use ($proof, $connection, $database, $original, $operation, $variant, &$new): void {
                if ($event->connection !== $connection || $event->connection->transactionLevel() !== 0 || $new !== null) {
                    return;
                }
                $claim = operationWitness(FieldScenario::schemaRow($database, $operation->id));

                if ($claim['generation'] <= $original['claim']['generation'] || $claim['claim_token'] === null || $claim['phase'] !== 'index') {
                    return;
                }
                $proof->check($original['claim']['claim_token'] !== $claim['claim_token'], 'Reclaim commits a fresh token before DDL');
                $new = $claim;

                if ($variant === 'stale-ack') {
                    Scenario::barrier('ddl-new-claim', ['claim' => $claim]);
                }
            });
            FieldScenario::advanceTo($scope, $operation, ['completed']);
            $proof->check($new !== null, 'A real successor claim is observed from an independent connection');
            $proof->same(INDEX_FIELD, FieldScenario::catalogue($scope)->field('tested_number')->id->value, 'Only the verified mapping publishes the field');
        });
        $final = $catalogue->index('public', $spec['name']);
        indexMatches($proof, $final, $spec);
        $proof->same(true, $final['ready'] && $final['valid'] && $final['live'], 'The final catalog object is usable');
        $repaired = $variant === 'interrupted';
        $proof->same($repaired ? 2 : 0, $ddlCount, 'The real worker repairs with drop and create or adopts with zero DDL');

        if ($repaired) {
            $proof->check($already['oid'] !== $final['oid'], 'The invalid object is replaced with a new verified OID');
        } else {
            $proof->same($already, $final, 'Adoption preserves the exact valid PostgreSQL catalog and physical index identity');
        }
        $row = FieldScenario::schemaRow($database, $operation->id);
        $receipt = json_decode($row->ddl_receipt, true, flags: JSON_THROW_ON_ERROR);
        $acknowledgements = $database->table('scenario_ddl_acks')->where('operation_id', $operation->id)->get();
        $proof->same(1, $acknowledgements->count(), 'Exactly one elected claimant updates the owner receipt');
        $acknowledgement = $acknowledgements->sole();
        $proof->same($new['generation'], (int) $acknowledgement->generation, 'The committed receipt uses the elected generation');
        $proof->same($new['claim_token'], $acknowledgement->claim_token, 'The committed receipt uses the elected token');
        $proof->same($receipt, json_decode($acknowledgement->receipt, true, flags: JSON_THROW_ON_ERROR), 'The observed update and persisted owner receipt agree');
        $proof->same((int) $final['oid'], (int) $receipt['oid'], 'The single owner receipt pins the verified index');
        $proof->same($spec['digest'], $receipt['digest'], 'The accepted receipt verifies the approved DDL digest');
        $proof->same(1, $database->table('sys_outbox_messages')->where('message_id', $row->changed_message)->where('name', 'fields.schema.changed')->count(), 'Exactly one V2 schema switch is committed');
        $proof->same($plan['neighbor'], $catalogue->neighborDigest(), 'A foreign same-name relation retains its content, OIDs and definitions');
        $proof->report($database, [$operation->id], ['index' => $already, 'old_claim' => $original['claim']], ['index' => $final, 'claim' => $new, 'owner_updates' => $acknowledgements->count(), 'receipt' => $receipt, 'ddl_statements' => $ddlCount, 'neighbor' => $catalogue->neighborDigest()]);

        break;

    case 'stale-observer':
        $new = Scenario::barrierRead('ddl-new-claim')['claim'];
        $row = operationWitness(FieldScenario::schemaRow($database, $operation->id));
        $acknowledgements = $database->table('scenario_ddl_acks')->where('operation_id', $operation->id)->count();
        $proof->same($new, $row, 'Independent SQL proves zero late-ACK updates against the new claim');
        $proof->same(null, $row['owner_receipt'], 'The newer owner ACK is still pending');
        $proof->same(0, $acknowledgements, 'No late owner receipt update committed');
        $proof->same(Scenario::barrierRead('ddl-before-ack')['index'], $catalogue->index('public', $spec['name']), 'A late ACK cannot replace the committed catalog object');
        $proof->report($database, [$operation->id], $new, ['owner_updates' => $acknowledgements, 'claim' => $row]);

        break;

    case 'negative':
        $wrong = $variant === 'wrong-definition';

        if ($wrong) {
            $database->statement('CREATE INDEX scenario_wrong_definition ON public.sys_attribute_documents (record_id)');
            $database->statement('ALTER INDEX public.scenario_wrong_definition RENAME TO "'.$spec['name'].'"');
        }
        $before = $catalogue->index('public', $spec['name']);
        $status = FieldScenario::within(static fn (DataScope $scope) => FieldScenario::advanceTo($scope, $operation, ['blocked']));
        $reason = $wrong ? 'physical-index-fingerprint-conflict' : 'direct-ddl-route-unavailable';
        $proof->check(in_array($reason, $status->blockers, true), 'The real DDL worker refuses the mismatched owner capability');
        $proof->same($before, $catalogue->index('public', $spec['name']), 'The rejected attempt leaves the catalog identical');
        $proof->same(false, headHasIndex($database, INDEX_FIELD), 'A blocked DDL attempt does not publish a mapping');
        $proof->same(null, FieldScenario::schemaRow($database, $operation->id)->ddl_receipt, 'A blocked DDL attempt creates no success receipt');
        $proof->same($plan['neighbor'], $catalogue->neighborDigest(), 'The same-name foreign relation remains unchanged');
        $proof->report($database, [$operation->id], ['index' => $before], ['state' => $status->state, 'reason' => $status->blockers, 'neighbor' => $catalogue->neighborDigest()]);

        break;

    default:
        throw new RuntimeException('Unknown installed ddl-claim action.');
}
