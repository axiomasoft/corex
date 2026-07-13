<?php

declare(strict_types=1);

use CoreX\Tests\Stubs\Widget;
use Flex\Modules\Enums\Capability;
use Flex\Modules\Exceptions\EntityNotFound;
use Flex\Modules\Registry\ArrayEntityRegistry;
use Flex\Modules\Registry\EntityDefinition;
use Illuminate\Database\Eloquent\Relations\Relation;

beforeEach(function (): void {
    $this->widgetDefinition = function (string $handle, array $capabilities = []): EntityDefinition {
        return new EntityDefinition(
            handle: $handle,
            model: Widget::class,
            table: 'widgets',
            module: 'corex/core-tests',
            labelSingular: 'Widget',
            labelPlural: 'Widgets',
            capabilities: $capabilities,
            fields: [],
            titleAttribute: 'name',
        );
    };
});

it('registers an entity and reads it back via get/has/forModel', function (): void {
    $registry = new ArrayEntityRegistry;
    $registry->register(($this->widgetDefinition)('core.widget'));

    expect($registry->has('core.widget'))->toBeTrue()
        ->and($registry->get('core.widget')->model)->toBe(Widget::class)
        ->and($registry->forModel(Widget::class)?->handle)->toBe('core.widget');
});

it('throws EntityNotFound for an unregistered handle', function (): void {
    $registry = new ArrayEntityRegistry;

    expect(fn () => $registry->get('core.missing'))->toThrow(EntityNotFound::class);
});

it('reports has() as false and forModel() as null for unregistered entities', function (): void {
    $registry = new ArrayEntityRegistry;

    expect($registry->has('core.missing'))->toBeFalse()
        ->and($registry->forModel(Widget::class))->toBeNull();
});

it('filters entities by capability', function (): void {
    $registry = new ArrayEntityRegistry;
    $registry->register(($this->widgetDefinition)('core.widget', [Capability::Auditable]));
    $registry->register(($this->widgetDefinition)('core.gadget'));

    $auditable = $registry->byCapability(Capability::Auditable);

    expect($auditable)->toHaveCount(1)
        ->and($auditable[0]->handle)->toBe('core.widget');
});

it('builds a morph map from registered handles that Relation::enforceMorphMap accepts', function (): void {
    $registry = new ArrayEntityRegistry;
    $registry->register(($this->widgetDefinition)('core.widget'));

    $map = [];

    foreach ($registry->all() as $definition) {
        $map[$definition->handle] = $definition->model;
    }

    Relation::enforceMorphMap($map);

    expect(Relation::getMorphedModel('core.widget'))->toBe(Widget::class);
});
