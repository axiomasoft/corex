<?php

declare(strict_types=1);

use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Data\IndexPolicy;
use CoreX\CustomFields\Internal\Schema\DatabaseFieldCatalogue;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Internal\Storage\ScopeConnectionGuard;
use CoreX\CustomFields\Tests\Pg\DatabaseFieldCataloguePgTestCase;
use CoreX\CustomFields\Tests\Support\PgTestSupport;
use CoreX\Database\Contracts\SchemaSets;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(DatabaseFieldCataloguePgTestCase::class)->group('pg');

function databaseCatalogue(): DatabaseFieldCatalogue
{
    $connection = DB::connection();

    return new DatabaseFieldCatalogue(
        connection: $connection,
        tables: PgTestSupport::tables(),
        guard: new ScopeConnectionGuard($connection),
        drivers: ['jsonb' => true, 'eav' => true],
    );
}

function databaseDefinition(
    string $id = '018f0b5d-0000-7000-8000-000000000001',
    int $revision = 1,
    string $key = 'website',
    AttributeLifecycle $lifecycle = AttributeLifecycle::Active,
    array $readKeys = [],
): AttributeDefinition {
    return new AttributeDefinition(
        id: AttributeId::v7($id),
        entity: 'contact',
        key: $key,
        revision: $revision,
        type: 'text',
        lifecycle: $lifecycle,
        required: false,
        searchable: true,
        constraints: ['max' => 255],
        readKeys: $readKeys,
        writeKeys: [],
        index: IndexPolicy::None,
    );
}

it('migrates and rolls back the three owned catalogue tables on an empty database', function (): void {

    expect(Schema::hasTable('sys_attribute_definitions'))->toBeTrue()
        ->and(Schema::hasTable('sys_attribute_key_reservations'))->toBeTrue()
        ->and(Schema::hasTable('sys_attribute_backfills'))->toBeTrue();

    $this->artisan('migrate:rollback', [
        '--database' => 'pgsql',
        '--path' => app(SchemaSets::class)->get('axiomasoft/corex-custom-fields:tenant')->path,
        '--realpath' => true,
        '--step' => 3,
    ])->assertSuccessful();

    expect(Schema::hasTable('sys_attribute_definitions'))->toBeFalse()
        ->and(Schema::hasTable('sys_attribute_key_reservations'))->toBeFalse()
        ->and(Schema::hasTable('sys_attribute_backfills'))->toBeFalse();

    $this->migrateSchemaSets(['axiomasoft/corex-custom-fields:tenant'], 'pgsql');
});

it('retains immutable revisions and projects only the latest revision', function (): void {
    $catalogue = databaseCatalogue();
    $first = databaseDefinition();
    $second = databaseDefinition(revision: 2);

    $catalogue->publish(jsonbScope(), $first, ['website' => 'current']);
    $catalogue->publish(jsonbScope(), $second, ['website' => 'current']);

    $snapshot = $catalogue->snapshot(jsonbScope(), 'contact');

    expect(DB::table('sys_attribute_definitions')->count())->toBe(2)
        ->and(DB::table('sys_attribute_key_reservations')->count())->toBe(1)
        ->and($snapshot->activeFields)->toHaveCount(1)
        ->and($snapshot->activeFields[0]['revision'])->toBe(2)
        ->and($snapshot->driverCapabilities)->toBe(['jsonb' => true, 'eav' => true]);
});

it('rolls back definition publication when another id owns the current key', function (): void {
    $catalogue = databaseCatalogue();
    $catalogue->publish(jsonbScope(), databaseDefinition(), ['website' => 'current']);
    $contender = databaseDefinition(id: '018f0b5d-0000-7000-8000-000000000002');

    expect(fn (): mixed => $catalogue->publish(jsonbScope(), $contender, ['website' => 'current']))
        ->toThrow(QueryException::class);

    expect(DB::table('sys_attribute_definitions')->count())->toBe(1)
        ->and(DB::table('sys_attribute_key_reservations')->value('attribute_id'))
        ->toBe('018f0b5d-0000-7000-8000-000000000001');
});

it('replaces the complete reservation set in the same transaction as a revision', function (): void {
    $catalogue = databaseCatalogue();
    $catalogue->publish(jsonbScope(), databaseDefinition(), ['website' => 'current']);
    $migrating = databaseDefinition(
        revision: 2,
        key: 'site_url',
        lifecycle: AttributeLifecycle::Migrating,
        readKeys: ['website'],
    );

    $catalogue->publish(jsonbScope(), $migrating, ['website' => 'alias', 'site_url' => 'current']);

    expect(DB::table('sys_attribute_key_reservations')->orderBy('key')->pluck('role', 'key')->all())
        ->toBe(['site_url' => 'current', 'website' => 'alias'])
        ->and(DB::table('sys_attribute_key_reservations')->pluck('current_revision')->unique()->all())
        ->toBe([2]);
});

it('rejects duplicate immutable revisions without changing reservations', function (): void {
    $catalogue = databaseCatalogue();
    $definition = databaseDefinition();
    $catalogue->publish(jsonbScope(), $definition, ['website' => 'current']);

    expect(fn (): mixed => $catalogue->publish(jsonbScope(), $definition, ['website' => 'current']))
        ->toThrow(QueryException::class);

    expect(DB::table('sys_attribute_definitions')->count())->toBe(1)
        ->and(DB::table('sys_attribute_key_reservations')->count())->toBe(1);
});

it('checks the tenant connection before reading definitions', function (): void {
    expect(fn (): mixed => databaseCatalogue()->snapshot(jsonbScope('pgsql:another_test'), 'contact'))
        ->toThrow(ScopeDenied::class);
});
