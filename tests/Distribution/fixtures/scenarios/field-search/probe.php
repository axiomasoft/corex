<?php

declare(strict_types=1);

use App\FieldSearch\EquipmentLocaleSource as Locale;
use App\FieldSearch\EquipmentOwner as Owner;
use App\Scenario\Scenario;
use App\Scenario\SearchScenarioMeili as Meili;
use App\Scenario\SearchScenarioTrace;
use CoreX\CustomFields\Query\Contracts\FieldFilter;
use CoreX\CustomFields\Query\Data\FilterNode;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Enums\FieldOperator;
use CoreX\CustomFields\Values\Contracts\FieldValues;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Enums\FieldUse;
use CoreX\Data\Enums\FieldUsePlanKind;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Search\Errors\Exceptions\GenerationChanged;
use CoreX\Search\Errors\Exceptions\IndexNotReady;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\SearchFilter;
use CoreX\Search\Query\Data\SearchHit;
use CoreX\Search\Query\Data\SearchPage;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\Query\Data\SearchSort;
use CoreX\SearchCustomFields\Invalidation\Contracts\FieldSearchSubscriber;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$database = Scenario::connection('a');
$trace = app(SearchScenarioTrace::class);
$mode = $argv[1] ?? '';

function searchEquipment(DataScope $scope, array $options = []): SearchPage
{
    return app(Search::class)->query($scope, new SearchQuery(Owner::entity(), ...$options));
}

function stores(Connection $db): array
{
    return [
        'outbox' => $db->table('sys_outbox_messages')->count(),
        'inbox' => $db->table('sys_inbox_messages')->count(),
        'dirty' => $db->table('corex_search_dirty')->count(),
        'broker' => Scenario::connection('control')->table('scenario_field_search_broker')->count(),
    ];
}

function assetValue(DataScope $scope, string $field): mixed
{
    $value = app(FieldValues::class)->read($scope, Owner::record(), new FieldSelection([$field]))->values[$field];

    return JsonDocuments::decode($value->document);
}

function refuses(string $exception, Closure $call, Scenario $proof, SearchScenarioTrace $trace, string $label): void
{
    $trace->reset();

    try {
        $call();
    } catch (Throwable $error) {
        if (! $error instanceof $exception) {
            throw $error;
        }
        $proof->same([], $trace->backend, $label);

        return;
    }

    throw new RuntimeException('Expected '.$exception.' for '.$label);
}

function delivery(Scenario $proof, string $id, string $expected): array
{
    $control = Scenario::connection('control');
    $prior = $control->table('scenario_field_search_broker')->where('id', $id)->sole();
    $receipt = app(FieldSearchSubscriber::class)->consume($id);
    $proof->same($expected, $receipt->value, 'Normal bridge consumes the exact durable broker wire');
    $after = $control->table('scenario_field_search_broker')->where('id', $id)->sole();
    $proof->same($prior->digest, $after->digest, 'Replay reads the same immutable broker bytes');
    $proof->same(null, $after->quarantine, 'The real contract and scope are admissible');

    return ['message' => $id, 'sha256' => $after->digest, 'receipt' => $receipt->value];
}

switch ($mode) {
    case 'produce':
    case 'independent':
        $database = Owner::install($proof);
        Owner::fieldChange(false);
        $initial = Owner::within(static fn (DataScope $scope) => Owner::update($scope, [Owner::SERIAL => 'baseline-pump', Owner::PRIORITY => '17'], 'initial-fields'));
        $relay = Owner::relay();
        $proof->same('published', $database->table('sys_outbox_messages')->where('message_id', $initial->messageId)->value('status'), 'The real relay drained fixture bootstrap facts');
        $target = Owner::within(static fn (DataScope $scope) => Owner::rebuild($scope, 'baseline-equipment'), service: true);
        $cursor = Owner::within(static function (DataScope $scope) use ($proof): string {
            $page = searchEquipment($scope, ['fields' => ['title'], 'limit' => 1]);
            $proof->same(Owner::ASSET, $page->hits[0]->record->id, 'The real initial index hydrates the non-id opaque key');
            $proof->check(is_string($page->cursor), 'A real previous-mapping cursor is available');

            return $page->cursor;
        });

        if ($mode === 'independent') {
            $proof->same(false, app()->bound(FieldSearchSubscriber::class), 'The absent or disabled bridge has no subscriber');
            $changed = Owner::within(static fn (DataScope $scope) => Owner::update($scope, [Owner::SERIAL => 'standalone-pump'], 'fields-alone'));
            $proof->same('standalone-pump', Owner::within(static fn (DataScope $scope) => assetValue($scope, Owner::SERIAL)), 'Real Fields update and read succeed independently');
            Owner::relay();
            $proof->same('published', $database->table('sys_outbox_messages')->where('message_id', $changed->messageId)->value('status'), 'Core relays the normal Fields fact independently of Search');
            $proof->same(0, $database->table('corex_search_dirty')->count(), 'An unavailable bridge admits no value invalidation');
            $proof->same(null, Meili::value(Meili::document($target, Owner::ASSET), Owner::SERIAL), 'The independently ready engine contains no custom-field projection');
            Owner::within(static function (DataScope $scope) use ($proof, $trace): void {
                refuses(SearchFeatureUnavailable::class, static fn () => searchEquipment($scope, ['text' => 'standalone', 'searchFields' => [Owner::SERIAL]]),
                    $proof, $trace, 'A missing bridge field is refused before backend lookup');
            });
            $proof->report($database, [$changed->messageId, $target->uid], [], ['value' => 'standalone-pump', 'subscriber' => false, 'dirty' => 0]);

            break;
        }

        $proof->same('baseline-pump', Meili::value(Meili::document($target, Owner::ASSET), Owner::SERIAL), 'The enabled bridge indexed the real stored stable custom field');
        $private = Locale::shape(Locale::PRIVATE_ID, 'en');
        $physical = Owner::within(static function (DataScope $scope) use ($private): string {
            $fields = app(SearchProjectionContributor::class)->describe(new ProjectionContext($scope, Owner::entity()))->fields;
            $descriptor = array_find($fields, static fn ($field): bool => $field->id === $private);

            return $descriptor?->physicalId ?? throw new RuntimeException('The real locale contributor has no physical key for the hidden shape.');
        }, service: true);
        $proof->check(preg_match('/\Al_[a-f0-9]{60}\z/D', $physical) === 1, 'Locale shape resolves through its backend-safe physical key');
        $proof->same('sealed-valve', Meili::value(Meili::document($target, Owner::ASSET), $physical), 'The private value genuinely exists in the current index');
        Scenario::barrier('equipment-ready', ['generation' => $target->generation, 'uid' => $target->uid, 'mapping' => $target->mappingDigest, 'cursor' => $cursor, 'stores' => stores($database)]);
        $proof->report($database, [$target->uid, $initial->messageId], [], ['mapping' => $target->mappingDigest, 'bootstrap_relay' => $relay]);

        break;

    case 'rollback':
        $before = Scenario::barrierRead('equipment-ready')['stores'];
        Owner::within(static function (DataScope $scope) use ($proof): void {
            $writer = DB::connection();
            $writer->beginTransaction();

            try {
                $save = Owner::update($scope, [Owner::SERIAL => 'rolled-back'], 'rollback-evidence');
                $proof->same('pending', $writer->table('sys_outbox_messages')->where('message_id', $save->messageId)->value('status'), 'The real fact is staged inside the owner transaction');
            } finally {
                $writer->rollBack();
            }
            $proof->same('baseline-pump', assetValue($scope, Owner::SERIAL), 'Rollback leaves the owner document at its old value');
        });
        $proof->same($before, stores($database), 'Rollback creates zero committed outbox, inbox, dirty or broker rows');
        $proof->same(0, $database->table('sys_attribute_write_tokens')->where('operation_key', 'rollback-evidence')->count(), 'Rollback leaves no committed write-token receipt');
        $proof->report($database, [Owner::ASSET], $before, stores($database));

        break;

    case 'commit':
        $before = Scenario::barrierRead('equipment-ready')['stores'];
        $save = Owner::within(static fn (DataScope $scope) => Owner::update($scope, [Owner::SERIAL => 'zeolite'], 'commit-pending'));
        $expected = [...$before, 'outbox' => $before['outbox'] + 1];
        $proof->same($expected, stores($database), 'Only the real owner outbox exists before the publisher can run');
        $proof->same('pending', $database->table('sys_outbox_messages')->where('message_id', $save->messageId)->value('status'), 'An independent session sees the durable unpublished field fact');
        $proof->report($database, [$save->messageId], $before, ['stores' => $expected, 'write_receipt' => $save->receiptId]);
        Scenario::barrier('equipment-committed', ['message' => $save->messageId]);

        throw new RuntimeException('The writer must be killed after its real commit and before relay.');
    case 'relay':
        $id = Scenario::barrierRead('equipment-committed')['message'];
        $proof->same('zeolite', Owner::within(static fn (DataScope $scope) => assetValue($scope, Owner::SERIAL)), 'A fresh process reads the committed Fields value after SIGKILL');
        $proof->same(0, Scenario::connection('control')->table('scenario_field_search_broker')->where('id', $id)->count(), 'No broker publication preceded the relay');
        $output = Owner::relay();
        $proof->same('published', $database->table('sys_outbox_messages')->where('message_id', $id)->value('status'), 'The installed polling relay marks the exact event published');
        $wire = Scenario::connection('control')->table('scenario_field_search_broker')->where('id', $id)->sole();
        $proof->same(hash('sha256', $wire->wire), $wire->digest, 'A durable independent broker retains the exact wire');
        $proof->report($database, [$id], [], ['relay' => $output, 'broker_wire' => $wire->digest]);

        break;

    case 'delivery':
    case 'duplicate':
        $id = Scenario::barrierRead('equipment-committed')['message'];
        $result = delivery($proof, $id);
        $proof->same(1, $database->table('sys_inbox_messages')->where('message_id', $id)->count(), 'Both deliveries observe one real Inbox claim');
        $seq = $database->table('corex_search_dirty')->where('event_id', $id)->sole();
        $proof->same(Owner::ASSET, $seq->record_id, 'The owner dirty row preserves the opaque custom PK');
        $proof->same(1, (int) $seq->seq, 'Two broker processes allocate one exact dirty sequence');
        $proof->report($database, [$id], [], ['delivery' => $result, 'sequence' => (int) $seq->seq]);

        break;

    case 'indexed':
        $state = Scenario::barrierRead('equipment-ready');
        $updated = Owner::within(static function (DataScope $scope) use ($proof, $state, $database): array {
            $row = Owner::confirm($scope, $state['generation'], 1);
            $target = Owner::target($scope, $state['generation']);
            $proof->same('zeolite', Meili::value(Meili::document($target, Owner::ASSET), Owner::SERIAL), 'The actual provider contains the committed field value after ACK');
            $submission = $database->table('corex_search_submissions')->where(['lane_key' => Owner::lane($database)->lane_key, 'last_seq' => 1])->sole();
            $task = Meili::task($submission->task_ref);
            $proof->same('succeeded', $task['status'], 'Meilisearch records the exact successful provider task');
            $proof->same($target->uid, $task['indexUid'], 'Task ACK targets the authoritative equipment UID');

            return ['uid' => $target->uid, 'watermark' => (int) $row->confirmed_seq, 'task' => $task];
        }, service: true);
        Owner::within(static function (DataScope $scope) use ($proof): void {
            $page = searchEquipment($scope, ['text' => 'zeolite', 'searchFields' => [Owner::SERIAL], 'fields' => [Owner::SERIAL]]);
            $hit = $page->hits[0];
            $proof->same(Owner::ENTITY, $hit->record->entity, 'Search returns the exact owner entity');
            $proof->same(Owner::ASSET, $hit->record->id, 'Search passes the opaque RecordRef to current owner hydration');
            $proof->same([Owner::SERIAL => 'zeolite'], $hit->projection->values, 'The final authorized projection reads the current Fields owner value');
        });
        $proof->report($database, [$updated['uid'], Owner::ASSET], [], $updated);

        break;

    case 'locale':
        $results = Owner::within(static function (DataScope $scope) use ($proof, $trace): array {
            $en = Locale::shape(Locale::PUBLIC_ID, 'en');
            $ru = Locale::shape(Locale::PUBLIC_ID, 'ru-RU');
            $zh = Locale::shape(Locale::PUBLIC_ID, 'zh-Hant-TW');
            $hidden = Locale::shape(Locale::PRIVATE_ID, 'en');
            $page = searchEquipment($scope, ['fields' => [$en, $ru, $zh, $hidden], 'limit' => 2]);
            $byId = array_column(array_map(static fn (SearchHit $hit): array => ['id' => $hit->record->id, 'projection' => $hit->projection->values], $page->hits), 'projection', 'id');
            $proof->same('Registry Pump', $byId[Owner::ASSET][$en], 'Default localized display reads the exact owner RecordRef');
            $proof->same('Насос', $byId[Owner::ASSET][$ru], 'Canonical ru-RU selects its translation');
            $proof->same('Registry Pump', $byId[Owner::ASSET][$zh], 'Missing zh-Hant-TW translation falls back to default');
            $proof->same('Control Valve', $byId[Owner::SECOND][$ru], 'Missing ru-RU translation follows the declared fallback chain');
            $proof->same('控制閥', $byId[Owner::SECOND][$zh], 'Script-region locale returns its untranslated canonical text');
            $proof->same(false, array_key_exists($hidden, $byId[Owner::ASSET]), 'The hidden localized value is absent from final projection');
            $proof->check(! str_contains(json_encode($page, JSON_THROW_ON_ERROR), 'sealed-'), 'Hidden localized text is absent from snippets, metadata and continuation');
            $localizedHit = searchEquipment($scope, ['text' => 'Насос', 'searchFields' => [$ru], 'fields' => [$ru]])->hits[0];
            $proof->same(Owner::ASSET, $localizedHit->record->id, 'Actual Meilisearch resolves permitted Cyrillic text through the physical key');
            $proof->same([$ru => 'Насос'], $localizedHit->projection->values, 'The public result preserves the semantic locale field ID');
            $policy = app(FieldUsePolicy::class);
            foreach ([FieldUse::Read, FieldUse::Search, FieldUse::Filter, FieldUse::Sort, FieldUse::Aggregate] as $use) {
                $proof->same(FieldUsePlanKind::Unconditional, $policy->plan($scope, Owner::entity(), new FieldUseRequest(new FieldSelection([$hidden]), $use))->kind,
                    'The role allows the semantic shape alone');
                $proof->same(FieldUsePlanKind::Denied, $policy->plan($scope, Owner::entity(), new FieldUseRequest(new FieldSelection([Locale::PRIVATE_ID]), $use))->kind,
                    'The underlying localized-field grant remains denied');
            }
            foreach ([
                'search' => ['text' => 'sealed-valve', 'searchFields' => [$hidden]],
                'filter' => ['filters' => [new SearchFilter($hidden, ['sealed-valve'])]],
                'sort' => ['sort' => [new SearchSort($hidden)]],
                'aggregate' => ['facets' => [$hidden]],
            ] as $use => $query) {
                refuses(SearchFeatureUnavailable::class, static fn () => searchEquipment($scope, $query), $proof, $trace, 'A hidden localized '.$use.' fails before calling the backend');
            }

            return $byId;
        });
        $proof->report($database, [Owner::ASSET, Owner::SECOND], [], ['locales' => $results]);

        break;

    case 'switch':
        $before = stores($database);
        $old = Scenario::barrierRead('equipment-ready');
        $operation = Owner::fieldChange(true);
        $head = Owner::within(static function (DataScope $scope) use ($proof, $trace, $old): array {
            $schema = app(FieldCatalogue::class)->snapshot($scope, Owner::entity());
            $proof->same('int', $schema->field('priority')->type->value, 'The effective Fields head now declares the actual new type');
            $proof->same(17, assetValue($scope, Owner::PRIORITY), 'The actual owner converter publishes an integer value');
            refuses(IndexNotReady::class, static fn () => searchEquipment($scope, ['fields' => [Owner::PRIORITY]]), $proof, $trace, 'The published old mapping is rejected before a ready replacement');
            refuses(GenerationChanged::class, static fn () => searchEquipment($scope, ['fields' => ['title'], 'limit' => 1, 'cursor' => $old['cursor']]), $proof, $trace, 'A previous cursor fails before backend lookup as soon as the schema changes');

            try {
                searchEquipment($scope, ['filters' => [new SearchFilter(Owner::PRIORITY, [17])]]);

                throw new RuntimeException('A new predicate escaped the stale mapping fence.');
            } catch (IndexNotReady|SearchFeatureUnavailable) {
                $proof->same([], $trace->backend, 'New typed predicate exposes no stale index result');
            }

            return ['revision' => $schema->revision->value, 'mapping' => $schema->mappingDigest];
        });
        $fact = $database->table('sys_outbox_messages')->where(['name' => 'fields.schema.changed', 'contract_version' => 2])
            ->whereRaw("payload->>'target' = ?", [$head['revision']])->sole();
        $proof->same('pending', $fact->status, 'The schema fence takes effect before the committed event is emitted by relay');
        $proof->same($before['broker'], stores($database)['broker'], 'No new broker delivery was needed for the independent schema fence');
        $proof->same($old['generation'], (int) Owner::lane($database)->active_generation, 'SQL still points to the old generation until delivery and rebuild');
        $proof->same(0, $database->table('corex_search_rebuilds')->where('state', 'requested')->count(), 'No rebuild request was manufactured by a query');
        Scenario::barrier('equipment-switched', ['message' => $fact->message_id, 'head' => $head, 'dirty' => $before['dirty']]);
        $proof->report($database, [$operation, $fact->message_id], $before, ['head' => $head, 'broker' => stores($database)['broker']]);

        break;

    case 'schema-relay':
        $state = Scenario::barrierRead('equipment-switched');
        $output = Owner::relay();
        $proof->same('published', $database->table('sys_outbox_messages')->where('message_id', $state['message'])->value('status'), 'The real poll relay publishes the separate v2 schema fact');
        $proof->same(1, Scenario::connection('control')->table('scenario_field_search_broker')->where('id', $state['message'])->count(), 'The independent broker durably receives the actual schema fact');
        $proof->report($database, [$state['message']], [], ['relay' => $output]);

        break;

    case 'schema-delivery':
    case 'schema-duplicate':
        $state = Scenario::barrierRead('equipment-switched');
        $received = delivery($proof, $state['message']);
        $requests = $database->table('corex_search_rebuilds')->where('state', 'requested');
        $row = $requests->sole();
        $proof->same($state['head']['revision'], $row->source_revision, 'One real durable request records the current Fields schema revision');
        $proof->same($state['head']['mapping'], $row->source_mapping_digest, 'The exact source mapping witness is preserved');
        $proof->same($state['dirty'], $database->table('corex_search_dirty')->count(), 'The entity-level schema event receives no synthetic record invalidation');
        $proof->same(1, $database->table('sys_inbox_messages')->where('message_id', $state['message'])->count(), 'The schema event has one durable Inbox claim');
        $proof->report($database, [$state['message'], $row->rebuild_id], [], ['delivery' => $received, 'mapping_request' => $row->rebuild_id]);

        break;

    case 'new-index':
        $before = Scenario::barrierRead('equipment-ready');
        $request = $database->table('corex_search_rebuilds')->where('state', 'requested')->sole();
        $target = Owner::within(static fn (DataScope $scope) => Owner::rebuild($scope, 'current-equipment-schema'), service: true);
        $proof->same('satisfied', $database->table('corex_search_rebuilds')->where('rebuild_id', $request->rebuild_id)->value('state'), 'The real publication satisfies the recorded request');
        $proof->same($request->mapping_digest, $target->mappingDigest, 'The new ready index serves the exact requested composite mapping');
        $proof->check($target->uid !== $before['uid'] && $target->mappingDigest !== $before['mapping'], 'The new physical UID holds its own verified mapping');
        $proof->same(17, Meili::value(Meili::document($target, Owner::ASSET), Owner::PRIORITY), 'Actual Meilisearch indexes the integer owner projection');
        Owner::within(static function (DataScope $scope) use ($proof, $trace, $before): void {
            $hit = searchEquipment($scope, ['text' => 'zeolite', 'searchFields' => [Owner::SERIAL], 'fields' => [Owner::PRIORITY]])->hits[0];
            $proof->same(Owner::ASSET, $hit->record->id, 'The new ready generation finds the same opaque equipment key');
            $proof->same([Owner::PRIORITY => 17], $hit->projection->values, 'The ready mapping hydrates the stable ID with its current type');
            $schema = app(FieldCatalogue::class)->snapshot($scope, Owner::entity());
            $filter = app(FieldFilter::class)->compile($scope, $schema, new FilterNode($schema->field('priority')->id, FieldOperator::Eq, FieldInput::from(17)));
            $proof->same(17, JsonDocuments::decode($filter->operand), 'The current owner codec compiles the new typed predicate');
            refuses(GenerationChanged::class, static fn () => searchEquipment($scope, ['fields' => ['title'], 'limit' => 1, 'cursor' => $before['cursor']]), $proof, $trace, 'The old cursor cannot cross the immutable generation boundary');
        });
        $proof->report($database, [$request->rebuild_id, $target->uid], $before, ['mapping' => $target->mappingDigest]);

        break;

    default:
        throw new RuntimeException('Unknown installed field-search action.');
}
