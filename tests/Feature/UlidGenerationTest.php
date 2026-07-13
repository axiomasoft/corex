<?php

declare(strict_types=1);

use CoreX\Enums\IdStrategy;
use CoreX\Tests\Stubs\Widget;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['corex.ids.strategy' => IdStrategy::Ulid]);
});

it('uses ULID string keys', function (): void {
    $widget = Widget::create(['name' => 'alpha']);

    expect($widget->getKeyName())->toBe('id')
        ->and($widget->getKeyType())->toBe('string')
        ->and($widget->getIncrementing())->toBeFalse()
        ->and(Str::isUlid($widget->id))->toBeTrue();
});

it('soft deletes', function (): void {
    $widget = Widget::create(['name' => 'alpha']);

    $widget->delete();

    expect(Widget::count())->toBe(0)
        ->and(Widget::withTrashed()->count())->toBe(1)
        ->and($widget->trashed())->toBeTrue();
});
