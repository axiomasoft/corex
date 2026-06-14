<?php

declare(strict_types=1);

use CoreX\Models\AuditLog;
use CoreX\Tests\Stubs\Widget;

it('records an append-only entry on create', function (): void {
    $widget = Widget::create(['name' => 'one']);

    $entry = AuditLog::query()->where('event', 'created')->sole();

    expect($entry->auditable_id)->toBe($widget->id)
        ->and($entry->changes)->toHaveKey('name')
        ->and($entry->changes['name']['new'])->toBe('one');
});

it('records the diff on update', function (): void {
    $widget = Widget::create(['name' => 'one']);

    $widget->update(['name' => 'two']);

    $entry = AuditLog::query()->where('event', 'updated')->sole();

    expect($entry->changes['name'])->toBe(['old' => 'one', 'new' => 'two']);
});
