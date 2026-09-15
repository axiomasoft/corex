<?php

declare(strict_types=1);

use CoreX\Enums\IdStrategy;
use CoreX\Tests\Stubs\Widget;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// A21: forcing the strategy in this beforeEach() alone is not enough — by
// this point RefreshDatabase has already migrated `widgets` under whichever
// strategy was the global default (now Uuid7), so the id column is uuid,
// not ulid. On sqlite that mismatch is invisible (both collapse to a text
// storage class and Str::isUlid() just checks string shape), which is
// exactly how the A4/A21 regression stayed green. Rebuild the table here,
// AFTER the config flip and BEFORE any assertion, so the schema itself is
// actually ulid-typed for the rest of the test (see the PG-typed variant of
// this same table build in ConfigurableIdTest for the assertion that would
// catch this on PG).
beforeEach(function (): void {
    config(['corex.ids.strategy' => IdStrategy::Ulid]);

    Schema::dropIfExists('widgets');
    (require __DIR__.'/../database/migrations/2026_06_14_100000_create_widgets_table.php')->up();
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
