<?php

declare(strict_types=1);

use CoreX\Registry\EntityRegistry;
use CoreX\Tests\Stubs\Widget;

it('registers entities and builds a morph map', function (): void {
    $registry = new EntityRegistry;
    $registry->register(Widget::class, ['alias' => 'widget', 'auditable' => true]);

    expect($registry->morphAlias(Widget::class))->toBe('widget')
        ->and($registry->classForAlias('widget'))->toBe(Widget::class)
        ->and($registry->morphMap())->toBe(['widget' => Widget::class])
        ->and($registry->definition(Widget::class)?->auditable)->toBeTrue();
});

it('derives a default snake-case alias from the class name', function (): void {
    $registry = new EntityRegistry;
    $registry->register(Widget::class);

    expect($registry->morphAlias(Widget::class))->toBe('widget');
});
