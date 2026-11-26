<?php

declare(strict_types=1);

use CoreX\Access\Access\Internal\WorkspaceScopeWiring;
use CoreX\Access\Tests\Stubs\FakeWorkspaceScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Enums\Entities\Capability;
use Packman\Modules\Testing\ArrayEntityRegistry;

// Each test below declares its OWN `new class extends Model {}` at its own
// source line: PHP treats every distinct anonymous-class expression site as
// a distinct class, so `Model::addGlobalScope()` — a real, static,
// class-level registration — cannot leak from one test into another. Reusing
// one factory function would collapse all call sites onto the SAME
// generated class and defeat that isolation.

// WorkspaceScopeWiring registers the REAL global scope class, not a marker.
it('wires the workspace global scope onto every model whose entity declares WorkspaceScoped', function (): void {
    $model = new class extends Model
    {
        protected $table = 'wiring_probe';

        protected $guarded = [];
    };

    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'wiring.wired',
        model: $model::class,
        table: 'wiring_probe',
        module: 'test',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [Capability::WorkspaceScoped],
        fields: [],
        titleAttribute: null,
    ));

    WorkspaceScopeWiring::wire($registry, FakeWorkspaceScope::class, static fn (string $class): Scope => app()->make($class));

    expect($model->getGlobalScopes())->toHaveKey(FakeWorkspaceScope::class);
});

// Negative first — a model whose entity does NOT declare the capability is left untouched.
it('does not wire the workspace global scope onto a model without the WorkspaceScoped capability', function (): void {
    $model = new class extends Model
    {
        protected $table = 'wiring_probe';

        protected $guarded = [];
    };

    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'wiring.unwired',
        model: $model::class,
        table: 'wiring_probe',
        module: 'test',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [],
        fields: [],
        titleAttribute: null,
    ));

    WorkspaceScopeWiring::wire($registry, FakeWorkspaceScope::class, static fn (string $class): Scope => app()->make($class));

    expect($model->getGlobalScopes())->toBe([]);
});

// boxed profile: no effective scope class ⇒ explicitly nothing wired, never an error.
it('wires nothing when the effective scope class is null (boxed profile)', function (): void {
    $model = new class extends Model
    {
        protected $table = 'wiring_probe';

        protected $guarded = [];
    };

    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'wiring.null-pin',
        model: $model::class,
        table: 'wiring_probe',
        module: 'test',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [Capability::WorkspaceScoped],
        fields: [],
        titleAttribute: null,
    ));

    WorkspaceScopeWiring::wire($registry, null, static fn (string $class): Scope => app()->make($class));

    expect($model->getGlobalScopes())->toBe([]);
});
