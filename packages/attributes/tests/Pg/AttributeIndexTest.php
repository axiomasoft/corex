<?php

declare(strict_types=1);

use CoreX\Attributes\Data\AttributeDefinition;
use CoreX\Attributes\Data\AttributeId;
use CoreX\Attributes\Data\AttributeLifecycle;
use CoreX\Attributes\Data\AttributeStoragePolicy;
use CoreX\Attributes\Data\IndexPolicy;
use CoreX\Attributes\Internal\Engine\EavAttributeStore;
use CoreX\Attributes\Internal\Engine\JsonbAttributeStore;
use CoreX\Attributes\Internal\Engine\QueuedSchemaOperations;
use CoreX\Attributes\Internal\Schema\AttributeIndexPlanner;
use CoreX\Attributes\Internal\Storage\ConfiguredAttributeStoreResolver;
use CoreX\Attributes\Internal\Storage\ResolvingAttributeStore;
use CoreX\Attributes\Support\FieldSelection;
use CoreX\Attributes\Support\RecordSelection;
use CoreX\Attributes\Support\ValuePatch;
use CoreX\Attributes\Tests\Support\PgTestSupport;
use CoreX\Support\Data\RecordRef;
use CoreX\Support\Data\WriteToken;
use CoreX\Attributes\Tests\PgTestCase;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('routes a mixed batch through both engines without reordering', function (): void {
    $catalogue = PgTestSupport::catalogue('contact', [['code' => 'email', 'type' => 'email']]);
    $dealCatalogue = PgTestSupport::catalogue('deal', [['code' => 'email', 'type' => 'email']]);
    $jsonb = new JsonbAttributeStore(connection: DB::connection(), tables: PgTestSupport::tables(), catalogue: $catalogue, authorizer: jsonbAuthorizer());
    $eav = new EavAttributeStore(connection: DB::connection(), tables: PgTestSupport::tables(), catalogue: $dealCatalogue, authorizer: jsonbAuthorizer());
    $store = new ResolvingAttributeStore(new ConfiguredAttributeStoreResolver(
        policy: new AttributeStoragePolicy(default: 'jsonb', entities: ['deal' => 'eav']),
        jsonb: $jsonb,
        eav: $eav,
    ));
    $contact = new RecordRef(entity: 'contact', id: 'mix-contact');
    $deal = new RecordRef(entity: 'deal', id: 'mix-deal');
    $store->write(jsonbScope(), $contact, new ValuePatch(values: ['email' => 'contact@example.test']), new WriteToken(operationKey: 'mix-c', payloadHash: str_repeat('a', 64), expectedRecordVersion: 'absent'));
    $store->write(jsonbScope(), $deal, new ValuePatch(values: ['email' => 'deal@example.test']), new WriteToken(operationKey: 'mix-d', payloadHash: str_repeat('b', 64), expectedRecordVersion: 'absent'));

    $batch = $store->loadMany(jsonbScope(), new RecordSelection(records: [$deal, $contact]), new FieldSelection(fields: ['email']));

    expect($batch->records[0]->values['email'])->toBe('deal@example.test')
        ->and($batch->records[1]->values['email'])->toBe('contact@example.test');
});

it('blocks expression index DDL when an existing value cannot cast', function (): void {
    $definition = indexDefinition(IndexPolicy::ExpressionBtree);
    DB::table('sys_attribute_documents')->insert([
        'entity' => 'contact',
        'record_id' => 'dirty-1',
        'values' => json_encode(['amount' => 'not-a-number'], JSON_THROW_ON_ERROR),
        'record_version' => str_repeat('c', 64),
    ]);

    expect(fn () => indexPlanner()->activate(jsonbScope(), $definition, str_repeat('f', 64), str_repeat('e', 64)))
        ->toThrow(InvalidArgumentException::class, 'Dirty cast');
    expect(DB::table('pg_indexes')->where('indexname', 'like', 'sys_attr_ix_%')->exists())->toBeFalse();
});

it('verifies an expression B-tree and orders ties by record id', function (): void {
    $definition = indexDefinition(IndexPolicy::ExpressionBtree);
    foreach (['b-record', 'a-record'] as $id) {
        DB::table('sys_attribute_documents')->insert([
            'entity' => 'contact',
            'record_id' => $id,
            'values' => json_encode(['amount' => 4], JSON_THROW_ON_ERROR),
            'record_version' => str_repeat('c', 64),
        ]);
    }

    $receipt = indexPlanner()->activate(jsonbScope(), $definition, str_repeat('f', 64), str_repeat('e', 64));

    expect($receipt->ddl)->toBeTrue()
        ->and($receipt->indexName)->toBe($plannerName = indexPlanner()->indexName($definition))
        ->and($receipt->orderBy)->toEndWith(', record_id')
        ->and(json_encode($receipt->explainAfter, JSON_THROW_ON_ERROR))->toContain($plannerName)
        ->and($receipt->indexName)->not->toContain('amount');
});

it('declares promoted storage without foreign DDL', function (): void {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $receipt = indexPlanner()->activate(jsonbScope(), indexDefinition(IndexPolicy::Promoted), str_repeat('f', 64), str_repeat('e', 64));
    $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
    DB::disableQueryLog();

    expect($receipt->mode)->toBe('promoted')
        ->and($receipt->ddl)->toBeFalse()
        ->and($receipt->owner)->toBe('subject')
        ->and(str_contains(strtolower($sql), 'alter'))->toBeFalse();
});

function indexDefinition(IndexPolicy $index): AttributeDefinition
{
    return new AttributeDefinition(
        id: AttributeId::generate(),
        entity: 'contact',
        key: 'amount',
        revision: 1,
        type: 'int',
        lifecycle: AttributeLifecycle::Active,
        required: false,
        searchable: false,
        constraints: [],
        readKeys: ['amount'],
        writeKeys: ['amount'],
        index: $index,
    );
}

function indexPlanner(): AttributeIndexPlanner
{
    return new AttributeIndexPlanner(
        connection: DB::connection(),
        tables: PgTestSupport::tables(),
        operations: new QueuedSchemaOperations(
            connection: DB::connection(),
            tables: PgTestSupport::tables(),
            catalogue: schemaCatalogue('contact', str_repeat('f', 64)),
            authorizer: jsonbAuthorizer(),
        ),
    );
}
