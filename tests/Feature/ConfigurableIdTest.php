<?php

declare(strict_types=1);

use CoreX\Contracts\SettingsScope;
use CoreX\Enums\IdStrategy;
use CoreX\Settings\DatabaseSettingsRepository;
use CoreX\Support\Schema\IdColumns;
use CoreX\Tests\Stubs\StubSettingDefaultsProvider;
use CoreX\Tests\Stubs\Widget;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('model key behaviour follows config(corex.ids.strategy)', function (IdStrategy $strategy, string $keyType, bool $incrementing): void {
    config(['corex.ids.strategy' => $strategy]);

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
    expect(Str::isUlid((new Widget)->newUniqueId()))->toBeTrue();

    config(['corex.ids.strategy' => IdStrategy::Uuid]);
    expect(Str::isUuid((new Widget)->newUniqueId()))->toBeTrue();

    config(['corex.ids.strategy' => IdStrategy::Uuid7]);
    expect(Str::isUuid((new Widget)->newUniqueId()))->toBeTrue();

    config(['corex.ids.strategy' => IdStrategy::Int]);
    expect((new Widget)->uniqueIds())->toBe([]);
});

it('defaults to the uuid7 strategy', function (): void {
    expect(config('corex.ids.strategy'))->toBe(IdStrategy::Uuid7);
});

it('uuid7 ids are time-sortable (monotonic across successive generations)', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Uuid7]);

    $widget = new Widget;
    $ids = array_map(static fn () => $widget->newUniqueId(), range(1, 5));
    $sorted = $ids;
    sort($sorted, SORT_STRING);

    expect($ids)->toBe($sorted);
});

it('IdColumns emits native uuid key columns under the uuid7 strategy', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Uuid7]);

    Schema::create('id_cols_probe', function (Blueprint $table): void {
        IdColumns::primary($table);
        IdColumns::reference($table, 'ref_id')->nullable();
    });

    expect(Schema::getColumnType('id_cols_probe', 'id'))->toBe('varchar')
        ->and(Schema::getColumnType('id_cols_probe', 'ref_id'))->toBe('varchar');

    Schema::drop('id_cols_probe');
});

it('IdColumns emits integer key columns under the int strategy', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Int]);

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

it('IdColumns emits string key columns under the ulid strategy', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Ulid]);

    Schema::create('id_cols_probe', function (Blueprint $table): void {
        IdColumns::primary($table);
        IdColumns::reference($table, 'ref_id')->nullable();
    });

    expect(Schema::getColumnType('id_cols_probe', 'id'))->toBe('varchar')
        ->and(Schema::getColumnType('id_cols_probe', 'ref_id'))->toBe('varchar');

    Schema::drop('id_cols_probe');
});

// Validation (г), P1.19: the ulid seam is only real if the actual sys_*
// migrations build under it AND a write lands. This MUST run on PG, never
// sqlite — sqlite collapses column types, so a ulid written into a hardcoded
// uuid('id') column would pass there and prove nothing (that is exactly defect
// A4). On PG a ulid string in a uuid column raises SQLSTATE 22P02, so the
// insert below is what pins IdColumns::primary against a hardcoded `uuid`
// regression (mutation-check: restore uuid('id') in sys_settings → this reds).
it('core tables build and accept a settings row under the ulid id strategy', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Ulid]);

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

    $repository->set('crm', 'theme', 'dark', SettingsScope::account());

    $id = DB::connection('pgsql')->table('sys_settings')->value('id');

    expect(Str::isUlid((string) $id))->toBeTrue()
        ->and($repository->get('crm', 'theme', SettingsScope::account(), 'fallback'))->toBe('dark');
})->group('pg');
