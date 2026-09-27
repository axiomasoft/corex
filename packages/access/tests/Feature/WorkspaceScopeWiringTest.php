<?php

declare(strict_types=1);

use CoreX\Auth\Access\WorkspaceScopeWiring;
use CoreX\Auth\Tests\Stubs\FakeWorkspaceScope;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\EntityDefinition;
use Illuminate\Database\Eloquent\Model;

// Each test below declares its OWN `new class extends Model {}` at its own
// source line: PHP treats every distinct anonymous-class expression site as
// a distinct class, so `Model::addGlobalScope()` — a real, static,
// class-level registration — cannot leak from one test into another. Reusing
// one factory function would collapse all call sites onto the SAME
// generated class and defeat that isolation.

// D115 — WorkspaceScopeWiring registers the REAL global scope class, not a marker.
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

    WorkspaceScopeWiring::wire($registry, FakeWorkspaceScope::class);

    expect($model->getGlobalScopes())->toHaveKey(FakeWorkspaceScope::class);
});

// Negative first (Code Guidance) — a model whose entity does NOT declare the capability is left untouched.
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

    WorkspaceScopeWiring::wire($registry, FakeWorkspaceScope::class);

    expect($model->getGlobalScopes())->toBe([]);
});

// AC-28 — boxed profile: no scope class pinned ⇒ silent skip, never an error.
it('silently skips wiring when the scope class is null (boxed profile, no config pin)', function (): void {
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

    WorkspaceScopeWiring::wire($registry, null);

    expect($model->getGlobalScopes())->toBe([]);
});

// Symmetric case — a pinned class that does not exist (e.g. corex/tenancy not installed) also silently skips.
it('silently skips wiring when the pinned scope class does not exist', function (): void {
    $model = new class extends Model
    {
        protected $table = 'wiring_probe';

        protected $guarded = [];
    };

    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'wiring.missing-class',
        model: $model::class,
        table: 'wiring_probe',
        module: 'test',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [Capability::WorkspaceScoped],
        fields: [],
        titleAttribute: null,
    ));

    WorkspaceScopeWiring::wire($registry, 'CoreX\\Auth\\Tests\\Stubs\\DoesNotExist');

    expect($model->getGlobalScopes())->toBe([]);
});
