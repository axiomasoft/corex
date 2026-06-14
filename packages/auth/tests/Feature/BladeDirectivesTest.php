<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('compiles @scope to an entity-scoped role check', function (): void {
    $compiled = Blade::compileString('@scope($role, $project)yes@endscope');

    expect($compiled)->toContain('hasScopedRole($role, $project)')
        ->and($compiled)->toContain('auth()->check()');
});

it('compiles @entity to an entity-scoped permission check', function (): void {
    $compiled = Blade::compileString('@entity($permission, $deal)yes@endentity');

    expect($compiled)->toContain('hasScopedPermission($permission, $deal)');
});
