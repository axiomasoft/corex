<?php

declare(strict_types=1);

use CoreX\Tests\Stubs\Widget;
use Packman\Modules\Data\Compiler\CompiledRegistry;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Enums\Entities\Capability;

// introducing Ownable/WorkspaceScoped must not shift the existing
// 7 cases' values or ordinal position; a mistake here would silently rewrite
// every already-compiled registry file's capability bitmask-by-position
// assumptions (there are none today, but toArray() persists by value, not
// ordinal, so this pins that contract explicitly).
it('declares exactly 9 Capability cases, the original 7 unshifted, plus Ownable/WorkspaceScoped', function (): void {
    expect(array_map(fn (Capability $case): string => $case->value, Capability::cases()))->toBe([
        'searchable',
        'auditable',
        'custom_fields',
        'workflowable',
        'commentable',
        'documentable',
        'soft_deletes',
        'ownable',
        'workspace_scoped',
    ]);
});

// The compiled-file round-trip (bootstrap/cache/corex_modules.php) must carry
// the two new capabilities through unharmed, alongside a pre-existing one.
it('round-trips Ownable/WorkspaceScoped through CompiledRegistry::toArray()/fromArray()', function (): void {
    $definition = new EntityDefinition(
        handle: 'axis.thing',
        model: Widget::class,
        table: 'axis_things',
        module: 'corex/core-tests',
        labelSingular: 'Thing',
        labelPlural: 'Things',
        capabilities: [Capability::Ownable, Capability::WorkspaceScoped, Capability::Auditable],
        fields: [],
        titleAttribute: null,
    );

    $compiled = new CompiledRegistry(
        entities: ['axis.thing' => $definition],
        entityFields: [],
        permissions: [],
        api: [],
        filament: [],
        menu: [],
        routes: [],
        workflow: [],
    );

    $array = $compiled->toArray();

    expect($array['entities']['axis.thing']['capabilities'])->toBe(['ownable', 'workspace_scoped', 'auditable']);

    $restored = CompiledRegistry::fromArray($array);

    expect($restored->entities['axis.thing']->capabilities)->toBe([Capability::Ownable, Capability::WorkspaceScoped, Capability::Auditable])
        ->and($restored)->toEqual($compiled);
});
