<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Registry\EntityDefinition;
use CoreX\Panels\Contracts\PanelCatalogue;
use CoreX\Panels\Contracts\PanelProvider;
use CoreX\Panels\Data\EntityReference;
use CoreX\Panels\Data\PanelAccess;
use CoreX\Panels\Data\PanelContext;
use CoreX\Panels\Data\PanelDescriptor;
use CoreX\Panels\Data\PanelReference;
use CoreX\Panels\Internal\ModulePanelCatalogue;
use CoreX\Panels\PanelsModule;
use CoreX\Panels\PanelsServiceProvider;

function panelContext(string $account = 'account-a', string $actorKind = 'guest', ?string $actorId = null): PanelContext
{
    return new PanelContext(new TenantContext(new AccountRef($account, $account, 'active', [], [])), $actorKind, $actorId);
}

function panelEntity(string $handle, array $capabilities): EntityDefinition
{
    return new EntityDefinition(
        handle: $handle,
        model: stdClass::class,
        table: str_replace('.', '_', $handle),
        module: 'example/'.str_replace('.', '-', $handle),
        labelSingular: 'Example',
        labelPlural: 'Examples',
        capabilities: $capabilities,
        fields: [],
        titleAttribute: null,
    );
}

/** @param array<string, EntityDefinition> $definitions @param array<string, list<EntityDefinition>> $active */
function panelsRegistry(array $definitions, array &$active): EntityRegistry
{
    $registry = Mockery::mock(EntityRegistry::class);
    $registry->shouldReceive('has')->andReturnUsing(static fn (string $handle): bool => isset($definitions[$handle]));
    $registry->shouldReceive('get')->andReturnUsing(static fn (string $handle): EntityDefinition => $definitions[$handle]);
    $registry->shouldReceive('all')->andReturnUsing(static function (TenantContext $context) use (&$active): array {
        return $active[$context->account->id] ?? [];
    });

    return $registry;
}

/** @param array<string, bool> $active */
function panelsModules(array &$active): ModuleRegistry
{
    $modules = Mockery::mock(ModuleRegistry::class);
    $modules->shouldReceive('isActive')->andReturnUsing(static function (string $module, TenantContext $context) use (&$active): bool {
        return $active[$context->account->id] ?? false;
    });

    return $modules;
}

function panelProvider(string $key, ?string $requiredCapability, Closure $policy, array $supportedActions = ['open']): PanelProvider
{
    return new class($key, $requiredCapability, $policy, $supportedActions) implements PanelProvider
    {
        /** @param list<string> $supportedActions */
        public function __construct(
            private readonly string $key,
            private readonly ?string $requiredCapability,
            private readonly Closure $policy,
            private readonly array $supportedActions,
        ) {}

        public function descriptor(): PanelDescriptor
        {
            return new PanelDescriptor(
                version: 'panel/1', key: $this->key, owningModule: 'example/panels',
                requiredCapability: $this->requiredCapability, supportedActions: $this->supportedActions,
                label: 'example.panels.'.$this->key,
            );
        }

        public function access(PanelContext $context, PanelReference $panel): PanelAccess
        {
            return ($this->policy)($context, $panel);
        }
    };
}

it('PAN-01: discovers only supported descriptors across two active neutral entities', function (): void {
    $note = panelEntity('example.note', [Capability::Auditable]);
    $asset = panelEntity('example.asset', [Capability::Documentable]);
    $active = ['account-a' => [$note, $asset]];
    $modules = ['account-a' => true];
    $allow = static fn (): PanelAccess => new PanelAccess(view: true, actions: ['open'], reason: null);
    $catalogue = new ModulePanelCatalogue([
        panelProvider('example.audit', 'auditable', $allow),
        panelProvider('example.documents', 'documentable', $allow),
    ], panelsRegistry(['example.note' => $note, 'example.asset' => $asset], $active), panelsModules($modules));

    expect(array_map(static fn (PanelDescriptor $descriptor): string => $descriptor->key, $catalogue->forEntity(panelContext(), new EntityReference('example.note', 'n-1'))))->toBe(['example.audit'])
        ->and(array_map(static fn (PanelDescriptor $descriptor): string => $descriptor->key, $catalogue->forEntity(panelContext(), new EntityReference('example.asset', 'a-1'))))->toBe(['example.documents']);
});

it('PAN-02: denies unknown entity, provider, panel and unavailable policy', function (): void {
    $note = panelEntity('example.note', [Capability::Auditable]);
    $active = ['account-a' => [$note]];
    $modules = ['account-a' => true];
    $policy = static function (PanelContext $context, PanelReference $panel): PanelAccess {
        if ($panel->panelId !== 'overview') {
            return PanelAccess::deny('panel_unavailable');
        }

        throw new RuntimeException('policy dependency unavailable');
    };
    $catalogue = new ModulePanelCatalogue([panelProvider('example.audit', 'auditable', $policy)], panelsRegistry(['example.note' => $note], $active), panelsModules($modules));

    expect($catalogue->forEntity(panelContext(), new EntityReference('example.unknown', 'x')))->toBe([])
        ->and($catalogue->access(panelContext(), new PanelReference('example.unknown', 'overview', null))->reason)->toBe('provider_unavailable')
        ->and($catalogue->access(panelContext(), new PanelReference('example.audit', 'missing', null))->reason)->toBe('panel_unavailable')
        ->and($catalogue->access(panelContext(), new PanelReference('example.audit', 'overview', null))->reason)->toBe('policy_unavailable');
});

it('fails closed when a provider returns an action outside its registered descriptor', function (): void {
    $note = panelEntity('example.note', [Capability::Auditable]);
    $active = ['account-a' => [$note]];
    $modules = ['account-a' => true];
    $catalogue = new ModulePanelCatalogue([
        panelProvider('example.audit', 'auditable', static fn (): PanelAccess => new PanelAccess(true, ['write'], null)),
    ], panelsRegistry(['example.note' => $note], $active), panelsModules($modules));

    expect($catalogue->access(panelContext(), new PanelReference('example.audit', 'overview', null))->reason)->toBe('invalid_policy_result');
});

it('PAN-03: hides absent or disabled providers without evaluating their policy at boot', function (): void {
    $note = panelEntity('example.note', [Capability::Auditable]);
    $active = ['account-a' => [$note]];
    $modules = ['account-a' => false];
    $policyCalls = 0;
    $policy = static function () use (&$policyCalls): PanelAccess {
        $policyCalls++;

        return new PanelAccess(view: true, actions: ['open'], reason: null);
    };
    $catalogue = new ModulePanelCatalogue([panelProvider('example.audit', 'auditable', $policy)], panelsRegistry(['example.note' => $note], $active), panelsModules($modules));

    expect((new ModulePanelCatalogue([], panelsRegistry(['example.note' => $note], $active), panelsModules($modules)))->forEntity(panelContext(), new EntityReference('example.note', 'n-1')))->toBe([])
        ->and($catalogue->forEntity(panelContext(), new EntityReference('example.note', 'n-1')))->toBe([])
        ->and($policyCalls)->toBe(0);
});

it('PAN-04: current entity and provider state revoke a descriptor that was already discovered', function (): void {
    $note = panelEntity('example.note', [Capability::Auditable]);
    $active = ['account-a' => [$note]];
    $modules = ['account-a' => true];
    $catalogue = new ModulePanelCatalogue([panelProvider('example.audit', 'auditable', static fn (): PanelAccess => new PanelAccess(true, ['open'], null))], panelsRegistry(['example.note' => $note], $active), panelsModules($modules));
    $reference = new PanelReference('example.audit', 'overview', new EntityReference('example.note', 'n-1'));

    expect($catalogue->forEntity(panelContext(), $reference->parent))->toHaveCount(1);

    $active['account-a'] = [];
    expect($catalogue->access(panelContext(), $reference)->reason)->toBe('entity_unavailable');

    $active['account-a'] = [$note];
    $modules['account-a'] = false;
    expect($catalogue->access(panelContext(), $reference)->reason)->toBe('provider_unavailable');
});

it('PAN-05: re-evaluates policy so a stale derived grant cannot outlive revoked parent authority', function (): void {
    $note = panelEntity('example.note', [Capability::Auditable]);
    $active = ['account-a' => [$note]];
    $modules = ['account-a' => true];
    $parentReadable = true;
    $catalogue = new ModulePanelCatalogue([
        panelProvider('example.audit', 'auditable', static function () use (&$parentReadable): PanelAccess {
            return $parentReadable ? new PanelAccess(true, ['open'], null) : PanelAccess::deny('parent_revoked');
        }),
    ], panelsRegistry(['example.note' => $note], $active), panelsModules($modules));
    $reference = new PanelReference('example.audit', 'overview', new EntityReference('example.note', 'n-1'));

    expect($catalogue->access(panelContext(), $reference)->view)->toBeTrue();

    $parentReadable = false;
    expect($catalogue->access(panelContext(), $reference))->toMatchObject(PanelAccess::deny('parent_revoked'));
});

it('PAN-06: allows an explicit independent guest policy but platform disable wins', function (): void {
    $note = panelEntity('example.note', [Capability::Auditable]);
    $active = ['account-a' => [$note]];
    $modules = ['account-a' => true];
    $catalogue = new ModulePanelCatalogue([
        panelProvider('example.guest', null, static fn (PanelContext $context, PanelReference $panel): PanelAccess => $context->actorKind === 'guest' && $panel->parent === null
            ? new PanelAccess(true, [], 'independent_guest')
            : PanelAccess::deny('not_independent_guest')),
    ], panelsRegistry(['example.note' => $note], $active), panelsModules($modules));
    $reference = new PanelReference('example.guest', 'welcome', null);

    expect($catalogue->forEntity(panelContext(), new EntityReference('example.note', 'n-1')))->toHaveCount(1)
        ->and($catalogue->access(panelContext(), $reference))->toMatchObject(new PanelAccess(true, [], 'independent_guest'));

    $modules['account-a'] = false;
    expect($catalogue->access(panelContext(), $reference)->reason)->toBe('provider_unavailable');
});

it('PAN-07: rejects duplicate keys and isolates current A/B state on a reused catalogue', function (): void {
    $note = panelEntity('example.note', [Capability::Auditable]);
    $active = ['account-a' => [$note], 'account-b' => []];
    $modules = ['account-a' => true, 'account-b' => false];
    $provider = panelProvider('example.audit', 'auditable', static fn (): PanelAccess => new PanelAccess(true, ['open'], null));
    $registry = panelsRegistry(['example.note' => $note], $active);
    $catalogue = new ModulePanelCatalogue([$provider], $registry, panelsModules($modules));

    expect(fn (): ModulePanelCatalogue => new ModulePanelCatalogue([$provider, $provider], $registry, panelsModules($modules)))
        ->toThrow(LogicException::class, 'Duplicate panel provider key')
        ->and($catalogue->forEntity(panelContext('account-a'), new EntityReference('example.note', 'n-1')))->toHaveCount(1)
        ->and($catalogue->forEntity(panelContext('account-b'), new EntityReference('example.note', 'n-1')))->toBe([])
        ->and($catalogue->forEntity(panelContext('account-a'), new EntityReference('example.note', 'n-1')))->toHaveCount(1);
});

it('validates trusted DTO boundaries', function (): void {
    expect(fn (): PanelContext => panelContext(actorKind: 'user'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): PanelContext => new PanelContext(panelContext()->tenant, 'guest', 'forged'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): PanelAccess => new PanelAccess(false, ['open'], null))->toThrow(InvalidArgumentException::class)
        ->and(fn (): PanelDescriptor => new PanelDescriptor('panel/2', 'x', 'x', null, [], 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): PanelDescriptor => new PanelDescriptor('panel/1', 'x', 'x', '', [], 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): PanelDescriptor => new PanelDescriptor('panel/1', 'x', 'x', null, [1], 'x'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): EntityReference => new EntityReference('', 'n-1'))->toThrow(InvalidArgumentException::class)
        ->and(fn (): PanelReference => new PanelReference('', 'overview', null))->toThrow(InvalidArgumentException::class);
});

it('registers the tagged catalogue and exposes the package module manifest', function (): void {
    $note = panelEntity('example.note', [Capability::Auditable]);
    $active = ['account-a' => [$note]];
    $modules = ['account-a' => true];
    $this->app->instance(EntityRegistry::class, panelsRegistry(['example.note' => $note], $active));
    $this->app->instance(ModuleRegistry::class, panelsModules($modules));
    $this->app->instance('panel.provider.audit', panelProvider('example.audit', 'auditable', static fn (): PanelAccess => new PanelAccess(true, ['open'], null)));
    $this->app->tag(['panel.provider.audit'], 'corex.panels.providers');
    (new PanelsServiceProvider($this->app))->register();

    expect($this->app->make(PanelCatalogue::class))
        ->toBeInstanceOf(ModulePanelCatalogue::class)
        ->and((new PanelsModule)->manifest()->composerName)->toBe('axiomasoft/corex-panels')
        ->and((new PanelsModule)->manifest()->tablePrefix)->toBe('pnl');
});
