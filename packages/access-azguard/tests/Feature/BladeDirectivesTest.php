<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('compiles @scope to an entity-scoped role check', function (): void {
    $compiled = Blade::compileString('@scope($role, $project)yes @endscope');

    expect($compiled)->toBe('<?php if (auth()->check() && auth()->user()->hasScopedRole($role, $project)): ?>yes <?php endif; ?>');
});

it('compiles @entity to an entity-scoped permission check', function (): void {
    $compiled = Blade::compileString('@entity($permission, $deal)yes @endentity');

    expect($compiled)->toBe('<?php if (auth()->check() && auth()->user()->hasScopedPermission($permission, $deal)): ?>yes <?php endif; ?>');
});
