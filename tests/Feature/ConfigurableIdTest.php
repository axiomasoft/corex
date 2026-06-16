<?php

declare(strict_types=1);

use CoreX\Enums\IdStrategy;
use CoreX\Support\Schema\IdColumns;
use CoreX\Tests\Stubs\Widget;
use Illuminate\Database\Schema\Blueprint;
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
    'int' => [IdStrategy::Int, 'int', true],
]);

it('generates the id kind matching the strategy', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Ulid]);
    expect(Str::isUlid((new Widget)->newUniqueId()))->toBeTrue();

    config(['corex.ids.strategy' => IdStrategy::Uuid]);
    expect(Str::isUuid((new Widget)->newUniqueId()))->toBeTrue();

    config(['corex.ids.strategy' => IdStrategy::Int]);
    expect((new Widget)->uniqueIds())->toBe([]);
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
