<?php

declare(strict_types=1);

use CoreX\CustomFields\Contracts\AttributeStore;
use CoreX\CustomFields\Contracts\FieldCatalogue;
use CoreX\CustomFields\Data\FieldSnapshot;
use CoreX\CustomFields\Data\ValuePatch;
use CoreX\CustomFields\Internal\InvalidAttributeValue;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Internal\Values\EavAttributeStore;
use CoreX\CustomFields\Internal\Values\JsonbAttributeStore;
use CoreX\CustomFields\Tests\PgTestCase;
use CoreX\CustomFields\Tests\Support\PgTestSupport;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\WriteToken;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

function p106PgStore(
    string $engine,
    FieldCatalogue $catalogue,
    DataScopeAuthorizer $authorizer,
): AttributeStore {
    $arguments = [
        'connection' => DB::connection(),
        'tables' => PgTestSupport::tables(),
        'catalogue' => $catalogue,
        'authorizer' => $authorizer,
    ];

    return $engine === 'jsonb'
        ? new JsonbAttributeStore(...$arguments)
        : new EavAttributeStore(...$arguments);
}

function p106AssertNoMutation(): void
{
    expect(DB::table('sys_attribute_documents')->count())->toBe(0)
        ->and(DB::table('sys_attribute_eav_values')->count())->toBe(0)
        ->and(DB::table('sys_attribute_eav_records')->count())->toBe(0)
        ->and(DB::table('sys_attribute_write_tokens')->count())->toBe(0);
}

it('denies each engine atomically before database mutation', function (string $engine): void {
    $actions = [];
    $authorizer = jsonbAuthorizer(function (
        $scope,
        string $entity,
        string $subjectId,
        array $fields,
        string $action,
    ) use (&$actions): bool {
        $actions[] = $action;

        return $action !== 'attributes.mutate';
    });
    $catalogue = PgTestSupport::catalogue('contact', [
        ['code' => 'email', 'id' => '018d5f56-6f83-7a36-b0ac-6eabce87c10b', 'type' => 'email'],
        ['code' => 'status', 'id' => '018d5f56-6f83-7a36-b0ac-6eabce87c10c', 'type' => 'text'],
    ]);

    expect(fn (): mixed => p106PgStore($engine, $catalogue, $authorizer)->write(
        jsonbScope(),
        new RecordRef('contact', 'guarded-'.$engine),
        new ValuePatch(['email' => 'owner@example.test', 'status' => 'open']),
        new WriteToken('guarded-'.$engine, hash('sha256', $engine), 'absent'),
    ))->toThrow(InvalidAttributeValue::class, 'The attribute write is not allowed.');

    expect($actions)->toBe([
        'attributes.subject', 'attributes.subject',
        'attributes.view', 'attributes.view',
        'attributes.write', 'attributes.write',
        'attributes.mutate',
    ]);
    p106AssertNoMutation();
})->with(['jsonb', 'eav']);

it('checks the tenant connection before catalogue or database mutation', function (string $engine): void {
    $counter = (object) ['calls' => 0];
    $catalogue = new readonly class($counter) implements FieldCatalogue
    {
        public function __construct(private object $counter) {}

        public function snapshot(DataScope $scope, string $entity): FieldSnapshot
        {
            $this->counter->calls++;

            return PgTestSupport::catalogue($entity, [['code' => 'email', 'type' => 'email']])
                ->snapshot($scope, $entity);
        }
    };

    expect(fn (): mixed => p106PgStore($engine, $catalogue, jsonbAuthorizer())->write(
        jsonbScope(connectionIdentity: 'pgsql:another_test'),
        new RecordRef('contact', 'wrong-tenant-'.$engine),
        new ValuePatch(['email' => 'owner@example.test']),
        new WriteToken('wrong-tenant-'.$engine, hash('sha256', 'wrong-'.$engine), 'absent'),
    ))->toThrow(ScopeDenied::class);

    expect($counter->calls)->toBe(0);
    p106AssertNoMutation();
})->with(['jsonb', 'eav']);

it('rejects stale schema and invalid batch constraints without partial writes', function (string $engine): void {
    $catalogue = PgTestSupport::catalogue('contact', [
        ['code' => 'email', 'id' => '018d5f56-6f83-7a36-b0ac-6eabce87c10b', 'type' => 'email'],
        ['code' => 'status', 'id' => '018d5f56-6f83-7a36-b0ac-6eabce87c10c', 'type' => 'text', 'constraints' => ['enum' => ['open', 'closed']]],
    ]);
    $store = p106PgStore($engine, $catalogue, jsonbAuthorizer());

    expect(fn (): mixed => $store->write(
        jsonbScope(),
        new RecordRef('contact', 'stale-'.$engine),
        new ValuePatch(['email' => 'owner@example.test'], expectedSchemaHash: str_repeat('b', 64)),
        new WriteToken('stale-'.$engine, hash('sha256', 'stale-'.$engine), 'absent'),
    ))->toThrow(InvalidAttributeValue::class, 'The attribute write is not allowed.');

    expect(fn (): mixed => $store->write(
        jsonbScope(),
        new RecordRef('contact', 'constraints-'.$engine),
        new ValuePatch(['email' => 'owner@example.test', 'status' => 'pending']),
        new WriteToken('constraints-'.$engine, hash('sha256', 'constraints-'.$engine), 'absent'),
    ))->toThrow(InvalidAttributeValue::class, 'The attribute write is not allowed.');

    p106AssertNoMutation();
})->with(['jsonb', 'eav']);
