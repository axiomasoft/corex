<?php

declare(strict_types=1);

use CoreX\Access\Access\Internal\EffectiveWorkspaceScope;
use CoreX\Access\Laravel\AccessIntegrationRegistrar;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Tests\Stubs\FakeWorkspaceScope;
use CoreX\Context\Laravel\WorkspaceScopeProvider;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Data\EntityDefinition;
use CoreX\Modules\Enums\Capability;
use CoreX\Modules\Testing\ArrayEntityRegistry;
use Illuminate\Database\Eloquent\Model;

// Fail-closed access → tenancy isolation scope (audit P-04). Every test boots
// the real registrar against fresh option/scope singletons; each declares its
// OWN anonymous model so static global-scope registration cannot leak.

/** @param  class-string<Model>  $model */
function bootAccessWithWorkspaceScopedModel(string $model, ?string $configured): void
{
    config(['corex-access.workspace_scope_class' => $configured]);
    app()->forgetInstance(AuthOptions::class);
    app()->forgetInstance(EffectiveWorkspaceScope::class);

    $registry = new ArrayEntityRegistry;
    $registry->register(new EntityDefinition(
        handle: 'scope.'.md5($model),
        model: $model,
        table: 'scope_probe',
        module: 'test',
        labelSingular: 'x',
        labelPlural: 'x',
        capabilities: [Capability::WorkspaceScoped],
        fields: [],
        titleAttribute: null,
    ));
    app()->instance(EntityRegistry::class, $registry);

    (new AccessIntegrationRegistrar)->boot(app());
}

it('fails at boot when the configured workspace scope class does not exist', function (): void {
    $model = new class extends Model {};

    expect(fn () => bootAccessWithWorkspaceScopedModel($model::class, 'CoreX\\Tenancy\\Laravel\\Scopes\\Missing'))
        ->toThrow(InvalidArgumentException::class, 'corex-access.workspace_scope_class [CoreX\\Tenancy\\Laravel\\Scopes\\Missing] does not exist');

    expect($model->getGlobalScopes())->toBe([]);
});

it('applies the scope registered through the kernel contract when tenancy is installed', function (): void {
    app()->instance(WorkspaceScopeProvider::class, new class implements WorkspaceScopeProvider
    {
        public function scopeClass(): string
        {
            return FakeWorkspaceScope::class;
        }
    });
    $model = new class extends Model {};

    bootAccessWithWorkspaceScopedModel($model::class, null);

    expect(resolve(EffectiveWorkspaceScope::class)->class)->toBe(FakeWorkspaceScope::class)
        ->and($model->getGlobalScopes())->toHaveKey(FakeWorkspaceScope::class);
});

it('applies explicitly no workspace scope without tenancy and without an override', function (): void {
    expect(app()->bound(WorkspaceScopeProvider::class))->toBeFalse();
    $model = new class extends Model {};

    bootAccessWithWorkspaceScopedModel($model::class, null);

    expect(resolve(EffectiveWorkspaceScope::class)->class)->toBeNull()
        ->and($model->getGlobalScopes())->toBe([]);
});

it('rejects an existing class that is not a scope even without workspace entities', function (): void {
    config(['corex-access.workspace_scope_class' => stdClass::class]);
    app()->forgetInstance(AuthOptions::class);
    app()->forgetInstance(EffectiveWorkspaceScope::class);
    app()->instance(EntityRegistry::class, new ArrayEntityRegistry);

    expect(fn () => (new AccessIntegrationRegistrar)->boot(app()))
        ->toThrow(InvalidArgumentException::class, 'must implement the Eloquent Scope contract');
});

it('validates the scope returned by a provider at boot', function (): void {
    app()->instance(WorkspaceScopeProvider::class, new class implements WorkspaceScopeProvider
    {
        public function scopeClass(): string
        {
            return stdClass::class;
        }
    });
    $model = new class extends Model {};

    expect(fn () => bootAccessWithWorkspaceScopedModel($model::class, null))
        ->toThrow(InvalidArgumentException::class, 'must implement the Eloquent Scope contract');
});
