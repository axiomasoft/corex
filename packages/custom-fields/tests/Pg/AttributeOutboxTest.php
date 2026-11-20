<?php

declare(strict_types=1);

use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\Integration\AttributeMessageContracts;
use CoreX\CustomFields\Internal\Integration\AttributeMessageTranslator;
use CoreX\CustomFields\Internal\Values\EavAttributeStore;
use CoreX\CustomFields\Internal\Values\JsonbAttributeStore;
use CoreX\CustomFields\Tests\PgTestCase;
use CoreX\CustomFields\Tests\Support\PgTestSupport;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Messaging\Exceptions\UnknownMessageContract;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\Time\Testing\FrozenClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(PgTestCase::class)->group('pg');

beforeEach(function (): void {
    Schema::dropIfExists('sys_outbox_messages');
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], DB::getDefaultConnection());
});

function p107Store(string $engine, bool $declareContract = true): AttributeStore
{
    $clock = new FrozenClock(new DateTimeImmutable('2026-09-24T12:00:00+00:00'));
    $contracts = $declareContract ? AttributeMessageContracts::all() : [];
    $arguments = [
        'connection' => DB::connection(),
        'current' => jsonbCurrent(),
        'tables' => PgTestSupport::tables(),
        'catalogue' => PgTestSupport::catalogue('contact', [
            ['code' => 'status', 'id' => '018d5f56-6f83-7a36-b0ac-6eabce87c10c', 'type' => 'text'],
            ['code' => 'email', 'id' => '018d5f56-6f83-7a36-b0ac-6eabce87c10b', 'type' => 'email'],
        ]),
        'authorizer' => jsonbAuthorizer(),
        'outbox' => MessagingFixtures::outbox(
            connection: DB::connection(),
            contracts: MessagingFixtures::contracts(contracts: $contracts),
            clock: $clock,
        ),
        'messageTranslator' => new AttributeMessageTranslator(new ArrayDataScopeCodec),
        'clock' => $clock,
    ];

    return $engine === 'jsonb'
        ? new JsonbAttributeStore(...$arguments)
        : new EavAttributeStore(...$arguments);
}

function p107Write(AttributeStore $store, string $engine): mixed
{
    return $store->write(
        jsonbScope(),
        new RecordRef('contact', 'outbox-'.$engine),
        new ValuePatch(['status' => 'open', 'email' => 'owner@example.test']),
        new WriteToken('outbox-'.$engine, hash('sha256', 'payload-'.$engine), 'absent'),
    );
}

it('commits one metadata-only outbox row and appends nothing on replay', function (string $engine): void {
    $store = p107Store($engine);
    $first = p107Write($store, $engine);
    $replay = p107Write($store, $engine);
    $rows = DB::table('sys_outbox_messages')->get();

    expect($first->status)->toBe('applied')
        ->and($replay->status)->toBe('replayed')
        ->and($rows)->toHaveCount(1);

    $row = $rows->first();
    $payload = json_decode((string) $row->payload, true, 16, JSON_THROW_ON_ERROR);

    expect($row->name)->toBe(AttributeMessageContracts::VALUES_CHANGED)
        ->and($row->contract_version)->toBe(1)
        ->and($row->correlation_id)->toBe('outbox-'.$engine)
        ->and($payload)->toBe([
            'changedAttributeIds' => [
                '018d5f56-6f83-7a36-b0ac-6eabce87c10b',
                '018d5f56-6f83-7a36-b0ac-6eabce87c10c',
            ],
            'changedKeys' => ['email', 'status'],
            'record' => ['entity' => 'contact', 'id' => 'outbox-'.$engine],
            'recordVersion' => $first->recordVersion,
            'schemaHash' => str_repeat('a', 64),
        ])
        ->and((string) $row->payload)->not->toContain('owner@example.test');
})->with(['jsonb', 'eav']);

it('rolls back the mutation and token when outbox admission fails', function (string $engine): void {
    expect(fn (): mixed => p107Write(p107Store($engine, declareContract: false), $engine))
        ->toThrow(UnknownMessageContract::class, 'MSG-002');

    expect(DB::table('sys_outbox_messages')->count())->toBe(0)
        ->and(DB::table('sys_attribute_documents')->count())->toBe(0)
        ->and(DB::table('sys_attribute_eav_values')->count())->toBe(0)
        ->and(DB::table('sys_attribute_eav_records')->count())->toBe(0)
        ->and(DB::table('sys_attribute_write_tokens')->count())->toBe(0);
})->with(['jsonb', 'eav']);
