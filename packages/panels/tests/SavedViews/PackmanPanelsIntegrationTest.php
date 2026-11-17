<?php

declare(strict_types=1);

use CoreX\Context\Contracts\Principal;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Enums\PrincipalType;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Panels\Laravel\PanelContexts;
use Packman\Data\Laravel\JsonDocuments;
use Packman\Panels\Contracts\Registry\PanelCatalogue;
use Packman\Panels\Exceptions\SavedViews\SavedViewException;
use Packman\Panels\SavedViews\Contracts\SavedViewRepository;
use Packman\Panels\SavedViews\Contracts\ViewAccessPolicy;
use Packman\Panels\SavedViews\Contracts\ViewSchemaProvider;
use Packman\Panels\SavedViews\Data\SaveViewInput;
use Packman\Panels\SavedViews\Data\ViewAction;
use Packman\Panels\SavedViews\Data\ViewContext;
use Packman\Panels\SavedViews\Data\ViewField;
use Packman\Panels\SavedViews\Data\ViewSchema;
use Packman\Panels\SavedViews\Data\ViewScope;
use Packman\Panels\SavedViews\Data\ViewSubject;
use Packman\Schema\Contracts\Migration\SchemaSets;

function panelsUserRequest(PrincipalType $type = PrincipalType::User): RequestContext
{
    $tenant = new TenantContext(new AccountRef('account-a', 'Account A', 'active', [], []), boxed: true);
    $principal = Mockery::mock(Principal::class);
    $principal->shouldReceive('principalType')->andReturn($type);
    $principal->shouldReceive('principalId')->andReturn('user-a');
    $request = Mockery::mock(RequestContext::class);
    $request->shouldReceive('tenant')->andReturn($tenant);
    $request->shouldReceive('principal')->andReturn($principal);

    return $request;
}

it('preserves the trusted tenant and user across both upstream contexts', function (): void {
    $request = panelsUserRequest();
    $panel = PanelContexts::forUser($request);
    $view = PanelContexts::savedViewsForUser($request);

    expect($panel->tenant)->toBe($request->tenant())
        ->and($view->tenant)->toBe($request->tenant())
        ->and($panel->actorId)->toBe('user-a')
        ->and($view->actorId)->toBe('user-a')
        ->and($panel->actorKind)->toBe('user')
        ->and($view->actorKind)->toBe('user');
});

it('refuses to project a non-user principal as a user', function (PrincipalType $type): void {
    $request = panelsUserRequest($type);

    expect(fn () => PanelContexts::forUser($request))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PanelContexts::savedViewsForUser($request))->toThrow(InvalidArgumentException::class);
})->with([PrincipalType::Agent, PrincipalType::ApiKey]);

it('registers one upstream schema owner and preserves default-deny access', function (): void {
    $this->migrateSchemaSets(['axiomasoft/packman-panels:tenant']);
    $sets = resolve(SchemaSets::class)->all();
    $owners = array_values(array_map(static fn ($set): string => $set->package, array_filter(
        $sets,
        static fn ($set): bool => str_contains($set->package, 'panels'),
    )));

    expect($owners)->toBe(['axiomasoft/packman-panels'])
        ->and(resolve(PanelCatalogue::class))->toBeInstanceOf(PanelCatalogue::class);

    expect(fn () => resolve(SavedViewRepository::class)->viewsFor(
        PanelContexts::savedViewsForUser(panelsUserRequest()),
        new ViewScope('sample.note', null, null, null),
    ))->toThrow(SavedViewException::class);
});

it('saves and compiles through the upstream public repository under the host policy', function (): void {
    $this->migrateSchemaSets(['axiomasoft/packman-panels:tenant']);
    app()->instance(ViewSchemaProvider::class, new class implements ViewSchemaProvider
    {
        public function schema(ViewContext $context, string $entityHandle): ViewSchema
        {
            return new ViewSchema('sample/1', $entityHandle, [
                new ViewField('title', 'string', ['eq'], true, true, true, true, 'title'),
            ]);
        }
    });
    app()->instance(ViewAccessPolicy::class, new class implements ViewAccessPolicy
    {
        public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool
        {
            return $context->actorKind === 'user' && $context->actorId === 'user-a';
        }
    });
    $context = PanelContexts::savedViewsForUser(panelsUserRequest());
    $repository = resolve(SavedViewRepository::class);
    $view = $repository->save($context, new SaveViewInput(
        id: null,
        expectedRevision: '',
        scope: new ViewScope('sample.note', null, null, null),
        kind: 'table',
        localizedName: ['en' => 'Notes'],
        config: JsonDocuments::capture([
            'version' => 'saved-view/1',
            'filters' => ['field' => 'title', 'op' => 'eq', 'value' => 'Test'],
            'sorts' => [],
            'columns' => ['title'],
            'groupBy' => null,
            'presentation' => [],
        ]),
    ));

    expect($view->revision)->toBe('1')
        ->and(JsonDocuments::array($repository->compile($context, $view->id)->filters))
        ->toBe(['field' => 'title', 'op' => 'eq', 'value' => 'Test']);
});
