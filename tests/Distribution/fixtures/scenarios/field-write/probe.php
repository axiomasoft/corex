<?php

declare(strict_types=1);

use App\Scenario\FieldPgWitness;
use App\Scenario\FieldScenario;
use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Query\Data\FilterNode;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemas;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Data\SchemaOperationRef;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\CustomFields\Values\Data\DocumentRevision;
use CoreX\CustomFields\Values\Data\FieldPatch;
use CoreX\CustomFields\Values\Data\FieldWriteReceipt;
use CoreX\CustomFields\Values\Data\RecordSelection;
use CoreX\CustomFields\Values\Data\RemoveField;
use CoreX\CustomFields\Values\Data\SetField;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Laravel\JsonDocuments;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$database = Scenario::connection('a');
$mode = $argv[1] ?? '';

const LEGACY = '01900000-0000-7000-8000-000000000021';
const DECIMAL = '01900000-0000-7000-8000-000000000022';
const MONEY = '01900000-0000-7000-8000-000000000023';
const MULTI = '01900000-0000-7000-8000-000000000024';
const TOKEN = 'equipment-concurrent-write';
const PRECISE = '9007199254740993.123456789';

function runtimeField(string $id, string $key, string $type, array $constraints = [], int $revision = 1): FieldDefinition
{
    return new FieldDefinition(new FieldId($id), FieldScenario::entity(), $key, new FieldTypeId($type), 'runtime',
        revision: $revision, constraints: $constraints, uses: [FieldUse::Read, FieldUse::Write, FieldUse::Filter]);
}

function fieldSet(string $id, mixed $value): SetField
{
    return new SetField(new FieldId($id), FieldInput::from($value));
}

function initializePatch(string $schema, string $inspection = ' a-42 '): FieldPatch
{
    return new FieldPatch([
        fieldSet(FieldScenario::SERIAL, 'EQ-READY'), fieldSet(FieldScenario::INSPECTION, $inspection), fieldSet(LEGACY, 'r-07'),
        fieldSet(DECIMAL, PRECISE), fieldSet(MONEY, ['currency' => 'USD', 'amount' => '17.25']), fieldSet(MULTI, ['fast', 'slow']),
    ], new SchemaRevision($schema), DocumentRevision::absent());
}

function modify(DataScope $scope, string $record, array $operations, string $key): FieldWriteReceipt
{
    $values = app(FieldValues::class);
    $reference = FieldScenario::record($record);
    $observed = $values->read($scope, $reference, new FieldSelection([]));
    $patch = new FieldPatch($operations, $observed->schemaRevision, $observed->valueRevision);

    return $values->update($scope, $reference, $patch, $patch->token($scope, $reference, $key));
}

function receipt(FieldWriteReceipt $receipt): array
{
    return ['receipt' => $receipt->receiptId, 'message' => $receipt->messageId, 'value_revision' => $receipt->valueRevision->value, 'schema_revision' => $receipt->schemaRevision->value];
}

function document(Connection $database, string $record = FieldScenario::RECORD_RACE): ?array
{
    $row = $database->table('sys_attribute_documents')->selectRaw('*, xmin::text AS tuple_version')
        ->where('entity', FieldScenario::ENTITY)->where('record_id', $record)->first();

    return $row === null ? null : ['revision' => $row->document_revision, 'tuple_version' => $row->tuple_version,
        'slots' => json_decode($row->slots, true, flags: JSON_THROW_ON_ERROR)];
}

function fieldSlots(array $document, string $field): array
{
    return array_filter($document['slots'], static fn (string $key): bool => str_starts_with($key, $field.'@'), ARRAY_FILTER_USE_KEY);
}

function facts(Connection $database, string $record = FieldScenario::RECORD_RACE): array
{
    return $database->table('sys_outbox_messages')->where('name', 'fields.values.changed')->where('contract_version', 2)
        ->whereRaw("subject->>'id' = ?", [$record])->orderBy('id')->pluck('message_id')->all();
}

function expectRejection(Scenario $proof, Closure $call, string $class, string $description, ?string $message = null): void
{
    try {
        $call();
    } catch (Throwable $error) {
        if (! $error instanceof $class || ($message !== null && $message !== $error->getMessage())) {
            throw $error;
        }
        $proof->check(true, $description);

        return;
    }

    throw new RuntimeException($description);
}

function visiblePair(DataScope $scope): array
{
    $batch = app(FieldValues::class)->readMany($scope, new RecordSelection([FieldScenario::record(), FieldScenario::record(FieldScenario::RECORD_BAD)]), new FieldSelection([LEGACY]));

    return array_map(static fn ($item): array => ['revision' => $item->schemaRevision->value, 'value' => JsonDocuments::decode($item->values[LEGACY]->document)], $batch->items);
}

if ($mode === 'produce') {
    $database = FieldScenario::install($proof, 'field-write');
    $ids = [FieldScenario::SERIAL, FieldScenario::INSPECTION, LEGACY, DECIMAL, MONEY, MULTI];
    foreach ([FieldScenario::RECORD_RACE, FieldScenario::RECORD_BAD, FieldScenario::RECORD_HIDDEN] as $record) {
        FieldScenario::grant($database, Scenario::USER_A, FieldScenario::record($record), [FieldUse::Read, FieldUse::Write]);
        FieldScenario::grant($database, Scenario::USER_A, FieldScenario::record($record), [FieldUse::Read, FieldUse::Write], $ids);
    }
    FieldScenario::grant($database, Scenario::USER_A, FieldScenario::record(), [FieldUse::Filter], [FieldScenario::INSPECTION]);
    FieldScenario::grant($database, Scenario::USER_B, FieldScenario::record(), [FieldUse::Read]);
    FieldScenario::grant($database, Scenario::USER_B, FieldScenario::record(), [FieldUse::Read], [FieldScenario::SERIAL]);
    $revision = FieldScenario::within(static function (DataScope $scope) use ($proof): string {
        $schema = FieldScenario::catalogue($scope);
        $proof->same(FieldScenario::INSPECTION, $schema->field('inspection_code')->id->value, 'The external declaration is loaded from the installed module');
        $proof->same('vendor.type', $schema->field('inspection_code')->type->value, 'The external type is active on the dotted entity');
        foreach ([
            runtimeField(LEGACY, 'legacy_code', 'text', ['minLength' => 4]),
            runtimeField(DECIMAL, 'precise_decimal', 'decimal', ['precision' => 30, 'scale' => 9]),
            runtimeField(MONEY, 'money_value', 'money'),
            runtimeField(MULTI, 'speed_modes', 'multiselect', ['choices' => ['fast', 'slow']]),
        ] as $definition) {
            $operation = FieldScenario::apply($scope, FieldSchemaChange::add($definition), 'add-'.$definition->machineKey);
            FieldScenario::advanceTo($scope, $operation, ['completed']);
        }

        return FieldScenario::catalogue($scope)->revision->value;
    });
    $proof->same(null, document($database), 'The first writer races on an absent document');
    Scenario::barrier('fields-ready', ['revision' => $revision]);
    $proof->report($database, $ids, [], ['schema_revision' => $revision]);

    return;
}
$schema = Scenario::barrierRead('fields-ready')['revision'];

switch ($mode) {
    case 'writer-a':
    case 'writer-b':
        $first = $mode === 'writer-a';
        $result = FieldScenario::within(static function (DataScope $scope) use ($first, $schema): array {
            $db = DB::connection();
            $backend = FieldPgWitness::markSession($db, $first ? 'writer-a' : 'writer-b');
            $record = FieldScenario::record();
            $patch = initializePatch($schema);
            $write = static fn () => receipt(app(FieldValues::class)->update($scope, $record, $patch, $patch->token($scope, $record, TOKEN)));

            if (! $first) {
                Scenario::barrier('token-b-ready', $backend);
                $result = $write();
                Scenario::barrier('token-replayed', $result);

                return $result;
            }

            return $db->transaction(static function () use ($write, $backend): array {
                $result = $write();
                Scenario::barrier('token-uncommitted', [...$backend, ...$result]);

                return $result;
            });
        });
        $proof->check(is_string($result['receipt']) && $result['receipt'] !== '', 'The real update returns a durable receipt');
        $proof->report(DB::connection(), [$result['receipt'], $result['message']], ['document' => 'absent'], $result);

        break;

    case 'token-observer':
        $first = Scenario::barrierRead('token-uncommitted');
        $second = Scenario::barrierRead('token-b-ready');
        $observer = new FieldPgWitness($database);
        FieldPgWitness::await('the second public writer blocks on the first document', static fn () => $observer->blockedBy($second['session'], $first['session']) ? true : null);
        $proof->check($first['session'] !== $second['session'], 'Independent writer sessions contend on the same owner document');
        $proof->same(null, document($database), 'The staged document is invisible to the independent session');
        $proof->same(0, $database->table('sys_attribute_write_tokens')->where('operation_key', TOKEN)->count(), 'The write token is uncommitted');
        $proof->same([], facts($database), 'The actual outbox fact remains uncommitted');
        $proof->report($database, [FieldScenario::RECORD_RACE], ['first' => $first['session'], 'second' => $second['session']], ['blocked' => true]);

        break;

    case 'verify-write':
        $first = Scenario::barrierRead('token-uncommitted');
        $second = Scenario::barrierRead('token-replayed');
        foreach (['receipt', 'message', 'value_revision', 'schema_revision'] as $key) {
            $proof->same($first[$key], $second[$key], 'Equal token writes return equal '.$key);
        }
        $proof->same([$first['message']], facts($database), 'One committed fact describes the one document revision');
        $proof->same(1, $database->table('sys_attribute_write_tokens')->where('operation_key', TOKEN)->count(), 'Only one write token was persisted');
        $proof->same($first['value_revision'], document($database)['revision'], 'The persisted document holds that one revision');
        $proof->same(['inspection:A-42'], array_values(fieldSlots(document($database), FieldScenario::INSPECTION)), 'The actual JSONB slot carries the external codec bytes');
        $fact = $database->table('sys_outbox_messages')->where('message_id', $first['message'])->firstOrFail();
        $payload = json_decode($fact->payload, true, flags: JSON_THROW_ON_ERROR);
        $proof->same($first['value_revision'], $payload['value_revision'], 'The immutable fact publishes the committed value revision');
        $proof->check(! str_contains($fact->payload, 'A-42'), 'The external value is absent from the fact payload');
        FieldScenario::within(static function (DataScope $scope) use ($proof, $schema): void {
            $values = app(FieldValues::class);
            $record = FieldScenario::record();
            $fields = new FieldSelection([FieldScenario::INSPECTION, DECIMAL, MONEY, MULTI]);
            $snapshot = $values->read($scope, $record, $fields);
            foreach ([FieldScenario::INSPECTION => 'A-42', DECIMAL => PRECISE, MULTI => ['fast', 'slow']] as $field => $expected) {
                $proof->same($expected, JsonDocuments::decode($snapshot->values[$field]->document), 'A scoped read returns the canonical value for '.$field);
            }
            $money = JsonDocuments::map($snapshot->values[MONEY]->document);
            $proof->same('USD', $money['currency'], 'Money keeps its currency');
            $proof->same('17.25', $money['amount'], 'Money keeps the exact decimal amount');
            $field = new FieldId(FieldScenario::INSPECTION);
            $filter = app(FieldFilter::class)->compile($scope, FieldScenario::catalogue($scope), new FilterNode($field, FieldOperator::Eq, FieldInput::from(' a-42 ')));
            $proof->same('inspection:A-42', JsonDocuments::decode($filter->operand), 'The installed filter compiles the external encoded operand');
            $proof->same([FieldScenario::RECORD_RACE], $filter->recordIds, 'The filter exposes one verified policy corpus');
            $proof->same($field->value, $filter->field->value, 'The filter addresses the stable field UUID');
            $proof->same(FieldOperator::Eq, $filter->operator, 'The public filter retains its declared operator');
            $proof->check(is_string($filter->policyWitness) && $filter->policyWitness !== '', 'The compiled filter is policy witnessed');
            $different = initializePatch($schema, 'b-23');
            expectRejection($proof, static fn () => $values->update($scope, $record, $different, $different->token($scope, $record, TOKEN)), RuntimeException::class, 'An altered request under the same token conflicts', 'custom-fields.idempotency-conflict');
            $copiedToken = initializePatch($schema)->token($scope, $record, TOKEN);
            expectRejection($proof, static fn () => $values->update($scope, $record, $different, new WriteToken(TOKEN, $copiedToken->payloadHash, 'absent')), RuntimeException::class, 'A copied client hash cannot conceal a changed patch', 'custom-fields.idempotency-conflict');
        });
        $otherPrincipal = FieldScenario::within(static fn (DataScope $scope): DataScope => $scope);
        FieldScenario::within(static function (DataScope $scope) use ($proof, $otherPrincipal): void {
            $values = app(FieldValues::class);
            $record = FieldScenario::record();
            $serial = new FieldSelection([FieldScenario::SERIAL]);
            expectRejection($proof, static fn () => $values->read($otherPrincipal, $record, $serial), ScopeContextMismatch::class, 'A valid scope for a different principal is rejected');
            $batch = $values->readMany($scope, new RecordSelection([$record, FieldScenario::record(FieldScenario::RECORD_HIDDEN), FieldScenario::record('missing')]), $serial);
            $proof->same([FieldScenario::RECORD_RACE], array_map(static fn ($item): string => $item->record->id, $batch->items), 'The batch exposes only the granted record');
            $proof->same([['position' => 1, 'reason' => 'unavailable'], ['position' => 2, 'reason' => 'unavailable']], $batch->rejections, 'Hidden and missing records have the same safe rejection');
            $restricted = $values->read($scope, $record, new FieldSelection([FieldScenario::SERIAL, FieldScenario::INSPECTION]));
            $proof->same([FieldScenario::SERIAL], array_keys($restricted->values), 'Record visibility does not disclose the separately restricted external field');
            expectRejection($proof, static fn () => modify($scope, FieldScenario::RECORD_RACE, [fieldSet(FieldScenario::SERIAL, 'forbidden')], TOKEN), RuntimeException::class, 'The restricted reader cannot write the otherwise readable field', 'custom-fields.access-denied');
            expectRejection($proof, static fn () => app(FieldFilter::class)->compile($scope, FieldScenario::catalogue($scope), new FilterNode(new FieldId(FieldScenario::SERIAL), FieldOperator::Eq, FieldInput::from('EQ-READY'))), RuntimeException::class, 'Read authority does not allow filter compilation', 'custom-fields.access-denied');
        }, Scenario::USER_B);
        $proof->same([$first['message']], facts($database), 'Denied writes and token conflicts leave the original fact unchanged');
        $proof->report($database, [$first['receipt'], $first['message']], [], ['receipt' => $second, 'fact' => $payload]);

        break;

    case 'evolution':
        $change = FieldScenario::within(static function (DataScope $scope) use ($proof, $database): array {
            $values = app(FieldValues::class);
            $record = FieldScenario::record();
            $serial = new FieldSelection([FieldScenario::SERIAL]);
            modify($scope, $record->id, [fieldSet(FieldScenario::SERIAL, null)], 'store-explicit-null');
            $proof->same('null', $values->read($scope, $record, $serial)->values[FieldScenario::SERIAL]->document->canonicalJson, 'An explicit JSON null is a present value');
            modify($scope, $record->id, [new RemoveField(new FieldId(FieldScenario::SERIAL))], 'remove-field');
            $proof->same([], $values->read($scope, $record, $serial)->values, 'Removal is an absent value');
            $before = document($database);
            $prior = facts($database);
            expectRejection($proof, static fn () => modify($scope, $record->id, [fieldSet(LEGACY, '100')], 'reject-short-text'), RuntimeException::class, 'Text 100 fails text minLength=4', 'custom-fields.invalid-field-value');
            $proof->same($before, document($database), 'Invalid text leaves the committed document identical');
            $proof->same($prior, facts($database), 'Invalid text emits no fact');
            $rename = FieldScenario::apply($scope, FieldSchemaChange::rename(FieldScenario::entity(), new FieldId(LEGACY), 'normalized_code'), 'rename-code');
            FieldScenario::advanceTo($scope, $rename, ['completed']);
            $after = FieldScenario::catalogue($scope);
            $proof->same(LEGACY, $after->field('normalized_code')->id->value, 'The schema rename keeps the stable field UUID');
            expectRejection($proof, static fn () => $after->field('legacy_code'), InvalidArgumentException::class, 'Old machine key is no longer resolvable');
            $proof->same('r-07', JsonDocuments::decode($values->read($scope, $record, new FieldSelection([LEGACY]))->values[LEGACY]->document), 'UUID-based reads survive the rename');
            modify($scope, FieldScenario::RECORD_BAD, [fieldSet(LEGACY, 'historical-invalid')], 'historical-old-value');
            $operation = FieldScenario::apply($scope, FieldSchemaChange::revise(runtimeField(LEGACY, 'normalized_code', 'vendor.type', revision: 3), 'vendor.inspection-code', 1), 'convert-code');
            $status = FieldScenario::advanceTo($scope, $operation, ['backfilling']);

            return ['operation' => $operation->id, 'initial_schema' => $after->revision->value, 'generation' => $status->generation->value];
        });
        $proof->same($change['initial_schema'], $database->table('sys_field_schema_heads')->where('entity', FieldScenario::ENTITY)->value('effective_revision'), 'Expand keeps the old physical mapping effective');
        Scenario::barrier('field-expanded', $change);
        $proof->report($database, [$change['operation']], [], $change);

        break;

    case 'dual-write':
        $checkpoint = Scenario::barrierRead('backfill-read');
        $previous = document($database);
        $prior = facts($database);
        $proof->same($checkpoint['document_revision'], $previous['revision'], 'The backfill read the earlier committed revision');
        $new = FieldScenario::within(static fn (DataScope $scope) => modify($scope, FieldScenario::RECORD_RACE, [fieldSet(LEGACY, 'r-08')], 'concurrent-dual-write'));
        $actual = document($database);
        $proof->same($new->valueRevision->value, $actual['revision'], 'The competing writer committed a fresh revision');
        $proof->check($actual['revision'] !== $previous['revision'], 'The source revision changed during backfill');
        $slots = array_values(fieldSlots($actual, LEGACY));
        sort($slots, SORT_STRING);
        $proof->same(['inspection:R-08', 'r-08'], $slots, 'One owner write populates the active and pending slots');
        $proof->same(count($prior) + 1, count(facts($database)), 'The dual write produces one user fact');
        Scenario::barrier('dual-write-committed', ['document' => $actual, 'facts' => facts($database)]);
        $proof->report($database, [$new->receiptId, $new->messageId], $previous, $actual);

        break;

    case 'backfill':
        $phase = Scenario::barrierRead('field-expanded');
        $due = new DateTimeImmutable(FieldScenario::schemaRow($database, $phase['operation'])->next_attempt_at);
        $clock = app(ScenarioClock::class);
        $clock->advance(max(0, $due->getTimestamp() - $clock->now()->getTimestamp()));
        FieldScenario::within(static fn () => FieldScenario::step());
        $written = Scenario::barrierRead('dual-write-committed');
        $proof->same($written['document'], document($database), 'The stale backfill CAS does not replace either slot or the logical revision');
        $proof->same($written['facts'], facts($database), 'Backfill emits no extra user-facing fact');
        $checkpoint = Scenario::barrierRead('backfill-read');
        $retry = $database->table('sys_attribute_backfills')->where([
            'operation_id' => $phase['operation'], 'kind' => 'retry', 'record_id' => $checkpoint['record'],
        ])->sole();
        $proof->same($checkpoint['document_revision'], $retry->seen_revision, 'A durable retry records the exact stale source revision');
        $proof->report($database, [$phase['operation']], $checkpoint, ['owner_updates' => 0, 'retry' => $retry]);

        break;

    case 'repair':
        $phase = Scenario::barrierRead('field-expanded');
        $written = Scenario::barrierRead('dual-write-committed')['document'];
        $operation = new SchemaOperationRef($phase['operation']);
        $next = FieldScenario::within(static function (DataScope $scope) use ($proof, $database, $phase, $operation, $written): string {
            $blocked = FieldScenario::advanceTo($scope, $operation, ['blocked']);
            $proof->check(str_contains(json_encode($blocked->failures, JSON_THROW_ON_ERROR), FieldScenario::RECORD_BAD), 'The historical converter failure is tied to its exact record');
            $proof->same($phase['initial_schema'], FieldScenario::catalogue($scope)->revision->value, 'Historical conversion failure blocks the effective head');
            $prior = document($database);
            $proof->check($prior['tuple_version'] !== $written['tuple_version'], 'The repeated backfill committed a new PostgreSQL tuple');
            $proof->same($written['revision'], $prior['revision'], 'The retry reads the new revision without changing the logical revision');
            $proof->same($written['slots'], $prior['slots'], 'The retry uses the new source value for both slots');
            $proof->same(false, $database->table('sys_attribute_backfills')->where([
                'operation_id' => $phase['operation'], 'kind' => 'retry', 'record_id' => FieldScenario::RECORD_RACE,
            ])->exists(), 'The worker settles the exact stale record retry');
            $before = facts($database);
            expectRejection($proof, static fn () => modify($scope, FieldScenario::RECORD_RACE, [fieldSet(LEGACY, 'invalid-live-conversion')], 'bad-dual-write'), InvalidArgumentException::class, 'Invalid pending conversion rolls back the user write');
            $proof->same($prior, document($database), 'Failed dual conversion preserves both previous slots');
            $proof->same($before, facts($database), 'Failed dual conversion emits no user fact');
            modify($scope, FieldScenario::RECORD_BAD, [fieldSet(LEGACY, 'x-10')], 'repair-historical-value');
            app(FieldSchemas::class)->retry($scope, $operation, $blocked->generation);
            FieldScenario::advanceTo($scope, $operation, ['verifying']);

            return FieldScenario::catalogue($scope)->revision->value;
        });
        Scenario::barrier('field-repaired', ['operation' => $operation->id, 'before_switch' => $next]);
        $proof->report($database, [$operation->id], ['state' => 'blocked'], ['effective' => $next]);

        break;

    case 'old-reader':
        $phase = Scenario::barrierRead('field-repaired');
        $values = FieldScenario::within(static function (DataScope $scope) use ($proof, $phase): array {
            $db = DB::connection();

            return $db->transaction(static function () use ($db, $proof, $scope, $phase): array {
                $pair = visiblePair($scope);
                $proof->same(array_fill(0, 2, $phase['before_switch']), array_column($pair, 'revision'), 'One pre-switch batch has one consistent old revision');
                $proof->same(['r-08', 'x-10'], array_column($pair, 'value'), 'The pre-switch reader sees both old typed values');
                Scenario::barrier('old-fields-read', ['session' => Scenario::describe($db)['session'], 'pair' => $pair]);

                return $pair;
            });
        });
        $proof->report(DB::connection(), [$phase['operation']], [], ['pair' => $values]);

        break;

    case 'switcher':
        $phase = Scenario::barrierRead('field-repaired');
        $switched = FieldScenario::within(static function (DataScope $scope) use ($phase): string {
            Scenario::barrier('schema-switch-ready', ['session' => Scenario::describe(DB::connection())['session']]);
            FieldScenario::advanceTo($scope, new SchemaOperationRef($phase['operation']), ['completed']);

            return FieldScenario::catalogue($scope)->revision->value;
        });
        $proof->check($phase['before_switch'] !== $switched, 'The effective schema changed through the real worker');
        $proof->report(DB::connection(), [$phase['operation']], ['effective' => $phase['before_switch']], ['effective' => $switched]);

        break;

    case 'switch-observer':
        $old = Scenario::barrierRead('old-fields-read');
        $new = Scenario::barrierRead('schema-switch-ready');
        $observer = new FieldPgWitness($database);
        $proof->check($new['session'] !== $old['session'], 'Schema switch and read use independent sessions');
        $phase = Scenario::barrierRead('field-repaired');
        $state = FieldPgWitness::await('the schema switch waits or commits after the old batch snapshot', static function () use ($observer, $old, $new, $database, $phase): ?string {
            if ($observer->blockedBy($new['session'], $old['session'])) {
                return 'reader-lock';
            }
            $head = $database->table('sys_field_schema_heads')->where('entity', FieldScenario::ENTITY)->value('effective_revision');

            return $head === $phase['before_switch'] ? null : 'switched';
        });
        $proof->check(in_array($state, ['reader-lock', 'switched'], true), 'PostgreSQL observes a real switch overlapping the old read');
        $proof->report($database, [$phase['operation']], [], ['state' => $state]);

        break;

    case 'verify-switch':
        $phase = Scenario::barrierRead('field-repaired');
        $pair = FieldScenario::within(visiblePair(...));
        $revision = $pair[0]['revision'];
        $proof->check($revision !== $phase['before_switch'], 'The fresh reader sees a new effective revision');
        $proof->same([$revision, $revision], array_column($pair, 'revision'), 'The fresh batch uses one consistent effective field mapping');
        $proof->same(['R-08', 'X-10'], array_column($pair, 'value'), 'The verified schema reads both corrected target values');
        $switch = $database->table('sys_outbox_messages')->where('name', 'fields.schema.changed')->where('contract_version', 2)
            ->whereRaw("payload->>'target' = ?", [$revision])->pluck('message_id')->all();
        $proof->same(1, count($switch), 'The committed switch emits exactly one V2 fact');
        $proof->same(1, count(fieldSlots(document($database), LEGACY)), 'Contract removes the retired slot once the old reader drains');
        $proof->report($database, [$phase['operation'], ...$switch], ['effective' => $phase['before_switch']], ['effective' => $revision, 'batch' => $pair]);

        break;

    default:
        throw new RuntimeException('Unknown installed field-write action.');
}
