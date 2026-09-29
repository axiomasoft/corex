<?php

declare(strict_types=1);

use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Data\IndexPolicy;
use CoreX\CustomFields\Internal\Schema\DatabaseFieldCatalogue;
use CoreX\CustomFields\Internal\Schema\DefinitionEvolution;
use CoreX\CustomFields\Internal\Storage\ScopeConnectionGuard;
use CoreX\CustomFields\Internal\Values\AttributeValueTransformerRegistry;
use CoreX\CustomFields\Internal\Values\EavAttributeStore;
use CoreX\CustomFields\Internal\Values\JsonbAttributeStore;
use CoreX\CustomFields\Internal\VersionConflict;
use CoreX\CustomFields\Support\FieldSelection;
use CoreX\CustomFields\Support\ValuePatch;
use CoreX\CustomFields\Tests\Pg\AttributeEvolutionPgTestCase;
use CoreX\CustomFields\Tests\Support\PgTestSupport;
use CoreX\Data\RecordRef;
use CoreX\Data\WriteToken;
use Illuminate\Support\Facades\DB;

uses(AttributeEvolutionPgTestCase::class)->group('pg');

function pgEvolutionRegistry(): AttributeValueTransformerRegistry
{
    return new AttributeValueTransformerRegistry([
        'numeric-text' => [
            'forward' => static fn (mixed $value): int => (int) $value,
            'reverse' => static fn (mixed $value): string => (string) $value,
        ],
    ]);
}

function pgEvolutionCatalogue(): DatabaseFieldCatalogue
{
    $connection = DB::connection();

    return new DatabaseFieldCatalogue(
        connection: $connection,
        tables: PgTestSupport::tables(),
        guard: new ScopeConnectionGuard($connection),
        drivers: ['jsonb' => true, 'eav' => true],
    );
}

function pgEvolutionDefinition(
    string $id = '018f0b5d-0000-7000-8000-000000000020',
    string $key = 'legacy_score',
): AttributeDefinition {
    return new AttributeDefinition(
        id: AttributeId::v7($id),
        entity: 'contact',
        key: $key,
        revision: 1,
        type: 'text',
        lifecycle: AttributeLifecycle::Active,
        required: false,
        searchable: true,
        constraints: [],
        readKeys: [$key],
        writeKeys: [$key],
        index: IndexPolicy::None,
    );
}

function pgEvolutionStore(
    string $engine,
    DatabaseFieldCatalogue $catalogue,
    AttributeValueTransformerRegistry $transformers,
): AttributeStore {
    $arguments = [
        'connection' => DB::connection(),
        'tables' => PgTestSupport::tables(),
        'catalogue' => $catalogue,
        'authorizer' => jsonbAuthorizer(),
        'transformers' => $transformers,
    ];

    return $engine === 'jsonb'
        ? new JsonbAttributeStore(...$arguments)
        : new EavAttributeStore(...$arguments);
}

it('dual reads and writes rename and type evolution on both engines', function (string $engine): void {
    $catalogue = pgEvolutionCatalogue();
    $transformers = pgEvolutionRegistry();
    $evolution = new DefinitionEvolution($transformers);
    $active = pgEvolutionDefinition();
    $catalogue->publish(jsonbScope(), $active, ['legacy_score' => 'current']);
    $store = pgEvolutionStore($engine, $catalogue, $transformers);

    $legacyRecord = new RecordRef(entity: 'contact', id: "{$engine}-legacy");
    $store->write(
        jsonbScope(),
        $legacyRecord,
        new ValuePatch(values: ['legacy_score' => '7']),
        new WriteToken(operationKey: "{$engine}-legacy-write", payloadHash: hash('sha256', "{$engine}-legacy-write"), expectedRecordVersion: 'absent'),
    );

    $expanded = $evolution->expand($active, 'score', 'int', 'numeric-text');
    $catalogue->publish(jsonbScope(), $expanded, $evolution->reservations($expanded));
    $expandedHash = $catalogue->snapshot(jsonbScope(), 'contact')->schemaHash;

    expect($store->read(jsonbScope(), $legacyRecord, new FieldSelection(fields: ['score']))->values)
        ->toBe(['score' => 7]);

    $currentRecord = new RecordRef(entity: 'contact', id: "{$engine}-current");
    $written = $store->write(
        jsonbScope(),
        $currentRecord,
        new ValuePatch(values: ['score' => 11], expectedSchemaHash: $expandedHash),
        new WriteToken(operationKey: "{$engine}-current-write", payloadHash: hash('sha256', "{$engine}-current-write"), expectedRecordVersion: 'absent'),
    );

    if ($engine === 'jsonb') {
        $stored = json_decode(DB::table('sys_attribute_documents')->where('record_id', $currentRecord->id)->value('values'), true, flags: JSON_THROW_ON_ERROR);
    } else {
        $stored = DB::table('sys_attribute_eav_values')
            ->where('record_id', $currentRecord->id)
            ->pluck('value', 'field')
            ->map(static fn (string $value): mixed => json_decode($value, true, flags: JSON_THROW_ON_ERROR))
            ->all();
    }

    expect($stored)->toMatchArray(['legacy_score' => '11', 'score' => 11])
        ->and($store->read(jsonbScope(), $currentRecord, new FieldSelection(fields: ['score']))->values)
        ->toBe(['score' => 11]);

    if ($engine === 'jsonb') {
        $stored['legacy_score'] = '99';
        DB::table('sys_attribute_documents')->where('record_id', $currentRecord->id)->update([
            'values' => json_encode($stored, JSON_THROW_ON_ERROR),
        ]);
    } else {
        DB::table('sys_attribute_eav_values')
            ->where('record_id', $currentRecord->id)
            ->where('field', 'legacy_score')
            ->update(['value' => json_encode('99', JSON_THROW_ON_ERROR)]);
    }

    expect($store->read(jsonbScope(), $currentRecord, new FieldSelection(fields: ['score']))->values)
        ->toBe(['score' => 11])
        ->and(fn (): mixed => $store->write(
            jsonbScope(),
            $currentRecord,
            new ValuePatch(values: ['score' => 12], expectedSchemaHash: $expandedHash),
            new WriteToken(operationKey: "{$engine}-drift-write", payloadHash: hash('sha256', "{$engine}-drift-write"), expectedRecordVersion: $written->recordVersion),
        ))->toThrow(VersionConflict::class, 'diverged');
})->with(['jsonb', 'eav']);

it('publishes expand switch and contract reservations atomically while retaining history', function (): void {
    $catalogue = pgEvolutionCatalogue();
    $evolution = new DefinitionEvolution(pgEvolutionRegistry());
    $active = pgEvolutionDefinition();
    $catalogue->publish(jsonbScope(), $active, $evolution->reservations($active));

    $expanded = $evolution->expand($active, 'score', 'int', 'numeric-text');
    $catalogue->publish(jsonbScope(), $expanded, $evolution->reservations($expanded));
    expect(DB::table('sys_attribute_key_reservations')->orderBy('key')->pluck('role', 'key')->all())
        ->toBe(['legacy_score' => 'alias', 'score' => 'current']);

    $digest = str_repeat('a', 64);
    $switched = $evolution->switch($expanded, $digest, $digest, $digest, new DateTimeImmutable('2026-09-24T10:00:00+00:00'));
    $catalogue->publish(jsonbScope(), $switched, $evolution->reservations($switched));
    expect(DB::table('sys_attribute_key_reservations')->orderBy('key')->pluck('role', 'key')->all())
        ->toBe(['legacy_score' => 'alias', 'score' => 'current']);

    $contracted = $evolution->contract($switched, new DateTimeImmutable('2026-09-25T10:00:00+00:00'), new DateInterval('PT12H'), 0);
    $catalogue->publish(jsonbScope(), $contracted, $evolution->reservations($contracted));

    expect(DB::table('sys_attribute_definitions')->where('attribute_id', $active->id->value)->count())->toBe(4)
        ->and(DB::table('sys_attribute_key_reservations')->pluck('role', 'key')->all())
        ->toBe(['score' => 'current'])
        ->and(DB::table('sys_attribute_key_reservations')->value('current_revision'))->toBe(4);
});

it('publishes both rollback projections without dropping immutable revisions', function (): void {
    $catalogue = pgEvolutionCatalogue();
    $evolution = new DefinitionEvolution(pgEvolutionRegistry());
    $active = pgEvolutionDefinition();
    $catalogue->publish(jsonbScope(), $active, $evolution->reservations($active));
    $expanded = $evolution->expand($active, 'score', 'int', 'numeric-text');
    $catalogue->publish(jsonbScope(), $expanded, $evolution->reservations($expanded));

    $beforeSwitch = $evolution->rollbackBeforeSwitch($expanded);
    $catalogue->publish(jsonbScope(), $beforeSwitch, $evolution->reservations($beforeSwitch));
    expect(DB::table('sys_attribute_key_reservations')->pluck('role', 'key')->all())
        ->toBe(['legacy_score' => 'current']);

    $expandedAgain = $evolution->expand($beforeSwitch, 'score', 'int', 'numeric-text');
    $catalogue->publish(jsonbScope(), $expandedAgain, $evolution->reservations($expandedAgain));
    $digest = str_repeat('b', 64);
    $switched = $evolution->switch($expandedAgain, $digest, $digest, $digest, new DateTimeImmutable);
    $catalogue->publish(jsonbScope(), $switched, $evolution->reservations($switched));
    $afterSwitch = $evolution->rollbackAfterSwitch($switched);
    $catalogue->publish(jsonbScope(), $afterSwitch, $evolution->reservations($afterSwitch));

    expect(DB::table('sys_attribute_key_reservations')->orderBy('key')->pluck('role', 'key')->all())
        ->toBe(['legacy_score' => 'current', 'score' => 'alias'])
        ->and(DB::table('sys_attribute_definitions')->where('attribute_id', $active->id->value)->count())->toBe(6);
});
