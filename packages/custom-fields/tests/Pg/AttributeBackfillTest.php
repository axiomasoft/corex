<?php

declare(strict_types=1);

use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Data\IndexPolicy;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\Schema\DatabaseFieldCatalogue;
use CoreX\CustomFields\Internal\Schema\DefinitionEvolution;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Internal\Storage\ScopeConnectionGuard;
use CoreX\CustomFields\Internal\Values\AttributeBackfill;
use CoreX\CustomFields\Internal\Values\AttributeValueTransformerRegistry;
use CoreX\CustomFields\Internal\Values\EavAttributeStore;
use CoreX\CustomFields\Internal\Values\JsonbAttributeStore;
use CoreX\CustomFields\Tests\Pg\AttributeBackfillPgTestCase;
use CoreX\CustomFields\Tests\Support\PgTestSupport;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use Illuminate\Support\Facades\DB;

uses(AttributeBackfillPgTestCase::class)->group('pg');

function backfillRegistry(): AttributeValueTransformerRegistry
{
    return new AttributeValueTransformerRegistry([
        'numeric-text' => [
            'forward' => static fn (mixed $value): int => (int) $value,
            'reverse' => static fn (mixed $value): string => (string) $value,
        ],
    ]);
}

function backfillCatalogue(): DatabaseFieldCatalogue
{
    $connection = DB::connection();

    return new DatabaseFieldCatalogue(
        connection: $connection,
        tables: PgTestSupport::tables(),
        guard: new ScopeConnectionGuard($connection),
        drivers: ['jsonb' => true, 'eav' => true],
    );
}

function backfillDefinition(): AttributeDefinition
{
    return new AttributeDefinition(
        id: AttributeId::v7('018f0b5d-0000-7000-8000-000000000030'),
        entity: 'contact',
        key: 'legacy_score',
        revision: 1,
        type: 'text',
        lifecycle: AttributeLifecycle::Active,
        required: false,
        searchable: true,
        constraints: [],
        readKeys: ['legacy_score'],
        writeKeys: ['legacy_score'],
        index: IndexPolicy::None,
    );
}

function backfillStore(
    string $engine,
    DatabaseFieldCatalogue $catalogue,
    AttributeValueTransformerRegistry $registry,
): AttributeStore {
    $arguments = [
        'connection' => DB::connection(),
        'tables' => PgTestSupport::tables(),
        'catalogue' => $catalogue,
        'authorizer' => jsonbAuthorizer(),
        'transformers' => $registry,
    ];

    return $engine === 'jsonb'
        ? new JsonbAttributeStore(...$arguments)
        : new EavAttributeStore(...$arguments);
}

/** @return array{DatabaseFieldCatalogue, AttributeDefinition, AttributeDefinition} */
function seedBackfill(string $engine, AttributeValueTransformerRegistry $registry, array $values): array
{
    $catalogue = backfillCatalogue();
    $active = backfillDefinition();
    $catalogue->publish(jsonbScope(), $active, ['legacy_score' => 'current']);
    $store = backfillStore($engine, $catalogue, $registry);
    foreach ($values as $recordId => $value) {
        $store->write(
            jsonbScope(),
            new RecordRef(entity: 'contact', id: $recordId),
            new ValuePatch(values: ['legacy_score' => (string) $value]),
            new WriteToken(operationKey: "{$engine}-{$recordId}", payloadHash: hash('sha256', "{$engine}-{$recordId}"), expectedRecordVersion: 'absent'),
        );
    }

    $evolution = new DefinitionEvolution($registry);
    $expanded = $evolution->expand($active, 'score', 'int', 'numeric-text');
    $catalogue->publish(jsonbScope(), $expanded, $evolution->reservations($expanded));

    return [$catalogue, $active, $expanded];
}

it('resumes ordered batches and verifies canonical digests on both engines', function (string $engine, string $operationId): void {
    $registry = backfillRegistry();
    [, $active, $expanded] = seedBackfill($engine, $registry, ['record-1' => 7, 'record-2' => 11]);
    $backfill = new AttributeBackfill(DB::connection(), PgTestSupport::tables(), $registry, $engine);
    $backfill->start(jsonbScope(), $operationId, $active, $expanded);
    $backfill->start(jsonbScope(), $operationId, $active, $expanded);

    $first = $backfill->runBatch(jsonbScope(), $operationId, 1);
    $second = $backfill->runBatch(jsonbScope(), $operationId, 1);
    $finished = $backfill->runBatch(jsonbScope(), $operationId, 1);
    $verified = $backfill->verify(jsonbScope(), $operationId);

    expect($first)->toMatchArray(['status' => 'backfilling', 'attempted' => 1, 'changed' => 1, 'cursor' => 'record-1'])
        ->and($second)->toMatchArray(['status' => 'backfilling', 'attempted' => 2, 'changed' => 2, 'cursor' => 'record-2'])
        ->and($finished['status'])->toBe('backfilled')
        ->and($verified['status'])->toBe('verified')
        ->and($verified['source_digest'])->toBe($verified['target_digest']);

    if ($engine === 'jsonb') {
        $stored = DB::table('sys_attribute_documents')->orderBy('record_id')->pluck('values')->map(
            static fn (string $value): array => json_decode($value, true, flags: JSON_THROW_ON_ERROR),
        )->all();
    } else {
        $stored = DB::table('sys_attribute_eav_values')->where('field', 'score')->orderBy('record_id')->pluck('value')->map(
            static fn (string $value): mixed => json_decode($value, true, flags: JSON_THROW_ON_ERROR),
        )->all();
    }

    expect($engine === 'jsonb' ? array_column($stored, 'score') : $stored)->toBe([7, 11]);
})->with([
    'jsonb' => ['jsonb', '018f0b5d-0000-7000-8000-000000000031'],
    'eav' => ['eav', '018f0b5d-0000-7000-8000-000000000032'],
]);

it('rolls back a failed batch and resumes it without advancing the cursor', function (): void {
    $failing = new AttributeValueTransformerRegistry([
        'numeric-text' => [
            'forward' => static function (mixed $value): int {
                if ((int) $value === 13) {
                    throw new RuntimeException('simulated worker termination');
                }

                return (int) $value;
            },
            'reverse' => static fn (mixed $value): string => (string) $value,
        ],
    ]);
    [, $active, $expanded] = seedBackfill('jsonb', $failing, ['record-1' => 13]);
    $operationId = '018f0b5d-0000-7000-8000-000000000033';
    $backfill = new AttributeBackfill(DB::connection(), PgTestSupport::tables(), $failing, 'jsonb');
    $backfill->start(jsonbScope(), $operationId, $active, $expanded);

    expect(fn (): mixed => $backfill->runBatch(jsonbScope(), $operationId, 10))
        ->toThrow(RuntimeException::class, 'simulated worker termination');

    $checkpoint = DB::table('sys_attribute_backfills')->where('id', $operationId)->first();
    expect($checkpoint->cursor_record_id)->toBeNull()
        ->and($checkpoint->attempted)->toBe(0)
        ->and($checkpoint->changed)->toBe(0);

    $resumed = new AttributeBackfill(DB::connection(), PgTestSupport::tables(), backfillRegistry(), 'jsonb');
    expect($resumed->runBatch(jsonbScope(), $operationId, 10)['changed'])->toBe(1)
        ->and($resumed->runBatch(jsonbScope(), $operationId, 10)['status'])->toBe('backfilled')
        ->and($resumed->verify(jsonbScope(), $operationId)['status'])->toBe('verified');
});

it('preserves a concurrent dual write and blocks switch after digest drift', function (): void {
    $registry = backfillRegistry();
    [$catalogue, $active, $expanded] = seedBackfill('eav', $registry, []);
    $store = backfillStore('eav', $catalogue, $registry);
    $record = new RecordRef(entity: 'contact', id: 'concurrent');
    $schemaHash = $catalogue->snapshot(jsonbScope(), 'contact')->schemaHash;
    $store->write(
        jsonbScope(),
        $record,
        new ValuePatch(values: ['score' => 17], expectedSchemaHash: $schemaHash),
        new WriteToken(operationKey: 'concurrent-write', payloadHash: hash('sha256', 'concurrent-write'), expectedRecordVersion: 'absent'),
    );

    $operationId = '018f0b5d-0000-7000-8000-000000000034';
    $backfill = new AttributeBackfill(DB::connection(), PgTestSupport::tables(), $registry, 'eav');
    $backfill->start(jsonbScope(), $operationId, $active, $expanded);
    expect($backfill->runBatch(jsonbScope(), $operationId, 10)['changed'])->toBe(0)
        ->and($backfill->runBatch(jsonbScope(), $operationId, 10)['status'])->toBe('backfilled');

    DB::table('sys_attribute_eav_values')
        ->where('entity', 'contact')
        ->where('record_id', 'concurrent')
        ->where('field', 'score')
        ->update(['value' => json_encode(99, JSON_THROW_ON_ERROR)]);

    expect(fn (): mixed => $backfill->verify(jsonbScope(), $operationId))
        ->toThrow(RuntimeException::class, 'divergent values');

    $receipt = DB::table('sys_attribute_backfills')->where('id', $operationId)->first();
    expect($receipt->status)->toBe('backfilled')
        ->and($receipt->source_digest)->not->toBe($receipt->target_digest)
        ->and($receipt->failed)->toBe(1);

    $evolution = new DefinitionEvolution($registry);
    expect(fn (): mixed => $evolution->switch(
        $expanded,
        $receipt->source_digest,
        $receipt->source_digest,
        $receipt->target_digest,
        new DateTimeImmutable,
    ))->toThrow(InvalidArgumentException::class, 'digest');
});

it('aborts an unfinished backfill and refuses to resume it', function (): void {
    $registry = backfillRegistry();
    [, $active, $expanded] = seedBackfill('jsonb', $registry, ['record-1' => 5]);
    $operationId = '018f0b5d-0000-7000-8000-000000000035';
    $backfill = new AttributeBackfill(DB::connection(), PgTestSupport::tables(), $registry, 'jsonb');

    expect(fn (): mixed => $backfill->start(jsonbScope('pgsql:another_test'), $operationId, $active, $expanded))
        ->toThrow(ScopeDenied::class);

    $backfill->start(jsonbScope(), $operationId, $active, $expanded);
    $backfill->abort(jsonbScope(), $operationId);

    expect(fn (): mixed => $backfill->runBatch(jsonbScope(), $operationId))
        ->toThrow(RuntimeException::class, 'cannot resume');
});
