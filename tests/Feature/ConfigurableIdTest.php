<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Database\Enums\IdStrategy;
use CoreX\Database\Laravel\IdColumns;
use CoreX\Settings\Data\SettingsScope;
use CoreX\Settings\Internal\DatabaseSettingsRepository;
use CoreX\Tests\Stubs\StubSettingDefaultsProvider;
use CoreX\Tests\Stubs\Widget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('model key behaviour follows config(corex.ids.strategy)', function (IdStrategy $strategy, string $keyType, bool $incrementing): void {
    config(['corex.ids.strategy' => $strategy]);
    app()->forgetInstance(CoreOptions::class);

    $widget = new Widget;

    expect($widget->getKeyType())->toBe($keyType)
        ->and($widget->getIncrementing())->toBe($incrementing);
})->with([
    'ulid' => [IdStrategy::Ulid, 'string', false],
    'uuid' => [IdStrategy::Uuid, 'string', false],
    'uuid7' => [IdStrategy::Uuid7, 'string', false],
    'int' => [IdStrategy::Int, 'int', true],
]);

it('generates the id kind matching the strategy', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Ulid]);
    app()->forgetInstance(CoreOptions::class);
    expect(Str::isUlid((new Widget)->newUniqueId()))->toBeTrue();

    config(['corex.ids.strategy' => IdStrategy::Uuid]);
    app()->forgetInstance(CoreOptions::class);
    expect(Str::isUuid((new Widget)->newUniqueId()))->toBeTrue();

    config(['corex.ids.strategy' => IdStrategy::Uuid7]);
    app()->forgetInstance(CoreOptions::class);
    expect(Str::isUuid((new Widget)->newUniqueId()))->toBeTrue();

    config(['corex.ids.strategy' => IdStrategy::Int]);
    app()->forgetInstance(CoreOptions::class);
    expect((new Widget)->uniqueIds())->toBe([]);
});

it('config/corex.php declares the uuid7 strategy', function (): void {
    expect(config('corex.ids.strategy'))->toBe(IdStrategy::Uuid7);
});

// A22: app(CoreOptions::class)->idStrategy must fall back to the SAME default as
// config/corex.php. A host app that publishes the config file with a
// mergeConfigFrom() flat-array replace (no 'ids.strategy' key at all) hits
// app(CoreOptions::class)->idStrategy's own default, not the package config file's — the
// two must not diverge. Simulate a genuinely missing key (offsetUnset), not
// a config('corex.ids.strategy', ...) read that would just echo whatever the
// package config file already merged in.
it('app(CoreOptions::class)->idStrategy defaults to uuid7 when the key is entirely absent', function (): void {
    // Repository::offsetUnset() on a dot key only nulls the leaf (Arr::set
    // semantics), it does not remove it — has() would still report the key
    // present. Replace the whole 'corex.ids' node with one that never had
    // 'strategy' in it, so the key is genuinely absent, the way a host app's
    // mergeConfigFrom() flat-array replace (A22) would leave it.
    config(['corex.ids' => Arr::except(config('corex.ids', []), ['strategy'])]);
    app()->forgetInstance(CoreOptions::class);

    expect(config()->has('corex.ids.strategy'))->toBeFalse()
        ->and(app(CoreOptions::class)->idStrategy)->toBe(IdStrategy::Uuid7);
});

it('uuid7 ids are time-sortable (monotonic across successive generations)', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Uuid7]);
    app()->forgetInstance(CoreOptions::class);

    $widget = new Widget;
    $ids = array_map(static fn () => $widget->newUniqueId(), range(1, 5));
    $sorted = $ids;
    sort($sorted, SORT_STRING);

    expect($ids)->toBe($sorted);
});

it('IdColumns emits integer key columns under the int strategy', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Int]);
    app()->forgetInstance(CoreOptions::class);

    Schema::create('id_cols_probe', function (Blueprint $table): void {
        IdColumns::primary($table);
        IdColumns::reference($table, 'ref_id')->nullable();
        IdColumns::foreign($table, 'fk_id')->nullable();
    });

    expect(Schema::getColumnType('id_cols_probe', 'id'))->toBe('integer')
        ->and(Schema::getColumnType('id_cols_probe', 'ref_id'))->toBe('integer')
        ->and(Schema::getColumnType('id_cols_probe', 'fk_id'))->toBe('integer');

    Schema::drop('id_cols_probe');
});

// A4: sqlite collapses uuid and char(26) down to the same 'varchar' storage
// class — a getColumnType() assertion there cannot tell uuid7 apart from
// ulid, so a IdStrategy::Uuid7 => $table->ulid() regression in IdColumns
// would stay green forever on that lane. The real type only exists on PG,
// read here straight from information_schema.columns.data_type.
it('IdColumns emits the id-strategy-correct native column type on PG (information_schema)', function (IdStrategy $strategy, string $expectedDataType): void {
    config(['corex.ids.strategy' => $strategy]);
    app()->forgetInstance(CoreOptions::class);

    Schema::connection('pgsql')->dropIfExists('id_cols_pg_probe');
    Schema::connection('pgsql')->create('id_cols_pg_probe', function (Blueprint $table): void {
        IdColumns::primary($table);
        IdColumns::reference($table, 'ref_id')->nullable();
        IdColumns::foreign($table, 'fk_id')->nullable();
    });

    $types = DB::connection('pgsql')
        ->table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', 'id_cols_pg_probe')
        ->whereIn('column_name', ['id', 'ref_id', 'fk_id'])
        ->pluck('data_type', 'column_name');

    expect($types['id'])->toBe($expectedDataType)
        ->and($types['ref_id'])->toBe($expectedDataType)
        ->and($types['fk_id'])->toBe($expectedDataType);

    Schema::connection('pgsql')->drop('id_cols_pg_probe');
})->with([
    'uuid7 -> uuid' => [IdStrategy::Uuid7, 'uuid'],
    'uuid -> uuid' => [IdStrategy::Uuid, 'uuid'],
    'ulid -> character(26)' => [IdStrategy::Ulid, 'character'],
    'int -> bigint' => [IdStrategy::Int, 'bigint'],
])->group('pg');

// Validation (г), P1.19: the ulid seam is only real if the actual sys_*
// migrations build under it AND a write lands. This MUST run on PG, never
// sqlite — sqlite collapses column types, so a ulid written into a hardcoded
// uuid('id') column would pass there and prove nothing (that is exactly defect
// A4). On PG a ulid string in a uuid column raises SQLSTATE 22P02, so the
// insert below is what pins IdColumns::primary against a hardcoded `uuid`
// regression (mutation-check: restore uuid('id') in sys_settings → this reds).
it('core tables build and accept a settings row under the ulid id strategy', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Ulid]);
    app()->forgetInstance(CoreOptions::class);

    DB::connection('pgsql')->statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::connection('pgsql')->statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(__DIR__.'/../../packages/core/database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    // The id column must be the ulid type (PG char(26) → 'bpchar'), NOT 'uuid'
    // — a hardcoded uuid('id') regression would report 'uuid' here.
    expect(Schema::connection('pgsql')->getColumnType('sys_settings', 'id'))->toBe('bpchar');

    $repository = new DatabaseSettingsRepository(
        defaults: new StubSettingDefaultsProvider([]),
        connection: 'pgsql',
    );

    $repository->set('crm', 'theme', JsonDocuments::capture('dark'), SettingsScope::account());

    $id = DB::connection('pgsql')->table('sys_settings')->value('id');

    expect(Str::isUlid((string) $id))->toBeTrue()
        ->and(JsonDocuments::decode($repository->get('crm', 'theme', SettingsScope::account(), JsonDocuments::capture('fallback'))))->toBe('dark');
})->group('pg');
