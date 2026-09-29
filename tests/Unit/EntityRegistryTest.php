<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Exceptions\EntityNotFound;
use CoreX\Modules\Registry\ArrayEntityRegistry;
use CoreX\Modules\Registry\EntityDefinition;
use CoreX\Modules\Registry\FieldDefinition;
use CoreX\Tests\Stubs\Widget;
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

it('returns the whole registry for all(null) and only active-module entities for all($ctx) (A38)', function (): void {
    // The predicate is the ModuleRegistry::isActive seam — per module AND per
    // ctx. all(null) must bypass it entirely (whole registry: morph map spans
    // disabled modules too); all($ctx) must apply it.
    $entityFor = static function (string $handle, string $module): EntityDefinition {
        return new EntityDefinition(
            handle: $handle,
            model: Widget::class,
            table: 'widgets',
            module: $module,
            labelSingular: 'Widget',
            labelPlural: 'Widgets',
            capabilities: [],
            fields: [],
            titleAttribute: 'name',
        );
    };

    $registry = new ArrayEntityRegistry(
        moduleActive: static fn (string $module, TenantContext $ctx): bool => $module === 'corex/on',
    );
    $registry->register($entityFor('on.widget', 'corex/on'));
    $registry->register($entityFor('off.widget', 'corex/off'));

    $ctx = new TenantContext(new AccountRef(id: 'acc-1', slug: 'acc-1', status: 'active', features: [], limits: []));

    $allHandles = array_map(static fn (EntityDefinition $d): string => $d->handle, $registry->all());
    $activeHandles = array_map(static fn (EntityDefinition $d): string => $d->handle, $registry->all($ctx));

    expect($allHandles)->toEqualCanonicalizing(['on.widget', 'off.widget'])
        ->and($activeHandles)->toBe(['on.widget']);
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

it('round-trips a FieldDefinition through toArray/fromArray (P1.15)', function (): void {
    $field = new FieldDefinition(
        handle: 'amount',
        type: 'money',
        required: true,
        searchable: true,
        meta: ['currency' => 'RUB', 'nested' => ['a', 'b']],
    );

    $rebuilt = FieldDefinition::fromArray($field->toArray());

    // No object survives into the persisted array — scalars/arrays only.
    expect($field->toArray())->toBe([
        'handle' => 'amount',
        'type' => 'money',
        'required' => true,
        'searchable' => true,
        'meta' => ['currency' => 'RUB', 'nested' => ['a', 'b']],
    ])->and($rebuilt)->toEqual($field);
});

it('round-trips an EntityDefinition — enums collapse to ->value, fields recurse (P1.15)', function (): void {
    $definition = new EntityDefinition(
        handle: 'core.widget',
        model: Widget::class,
        table: 'widgets',
        module: 'axiomasoft/corex',
        labelSingular: 'Widget',
        labelPlural: 'Widgets',
        capabilities: [Capability::Auditable, Capability::Searchable],
        fields: [new FieldDefinition('name', 'string', required: true)],
        titleAttribute: 'name',
    );

    $array = $definition->toArray();

    expect($array['capabilities'])->toBe(['auditable', 'searchable'])
        ->and($array['fields'][0]['handle'])->toBe('name')
        ->and(EntityDefinition::fromArray($array))->toEqual($definition);
});
