<?php

declare(strict_types=1);

use Brick\Money\Money;
use CoreX\Tests\Stubs\Widget;

it('casts money to minor units on write and back on read', function (): void {
    $widget = Widget::create([
        'name' => 'priced',
        'price' => Money::ofMinor(199900, 'RUB'),
    ]);

    expect($widget->getAttributes()['amount_minor'])->toBe(199900)
        ->and($widget->getAttributes()['currency'])->toBe('RUB');

    $fresh = Widget::findOrFail($widget->id);

    expect($fresh->price)->toBeInstanceOf(Money::class)
        ->and($fresh->price->getMinorAmount()->toInt())->toBe(199900)
        ->and($fresh->price->getCurrency()->getCurrencyCode())->toBe('RUB');
});

it('stores null when no money is set', function (): void {
    $widget = Widget::create(['name' => 'free']);

    expect($widget->price)->toBeNull();
});
