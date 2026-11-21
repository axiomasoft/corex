<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Panels\Contracts\SavedViewInventory;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packman\Data\Laravel\JsonDocuments;
use Packman\Panels\SavedViews\Contracts\SavedViewRepository;
use Packman\Panels\SavedViews\Contracts\ViewAccessPolicy;
use Packman\Panels\SavedViews\Contracts\ViewSchemaProvider;
use Packman\Panels\SavedViews\Data\SavedViewRef;
use Packman\Panels\SavedViews\Data\SaveViewInput;
use Packman\Panels\SavedViews\Data\ViewContext;
use Packman\Panels\SavedViews\Data\ViewField;
use Packman\Panels\SavedViews\Data\ViewSchema;
use Packman\Panels\SavedViews\Data\ViewScope;

beforeEach(function (): void {
    $database = config('database.connections.pgsql.database');

    if (! app()->environment('testing') || ! is_string($database) || ! str_ends_with($database, '_test')) {
        throw new RuntimeException('Saved-view PostgreSQL tests require an isolated *_test database.');
    }
    config(['database.default' => 'pgsql']);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->migrateSchemaSets(['axiomasoft/packman-panels:tenant'], 'pgsql');
});

it('creates the saved-view scope lock before the composite view foreign key', function (): void {
    expect(Schema::hasTable('sys_saved_view_scopes'))->toBeTrue()
        ->and(Schema::hasTable('sys_saved_views'))->toBeTrue();

    $foreignKeys = DB::select("select constraint_name from information_schema.table_constraints where table_name = 'sys_saved_views' and constraint_type = 'FOREIGN KEY'");

    expect($foreignKeys)->not->toBeEmpty();
})->group('pg');

it('saves a field search view for the verified service through its native system actor', function (): void {
    $tenant = resolve(TenantContextResolver::class)->current();
    $scope = WorkScopeFixture::install($this->app, 'search-fixture-index', 'service')->scope($tenant);
    $actor = new ViewContext($tenant, 'system', $scope->principalId);
    $viewScope = new ViewScope('csp.document', null, null, null);
    $field = '01900000-0000-7000-8000-000000000005';
    $filter = ['field' => $field, 'op' => 'eq', 'value' => 'PUMP-1'];
    $input = new SaveViewInput(null, '', $viewScope, 'table', ['en' => 'Pumps'], JsonDocuments::capture([
        'version' => 'saved-view/1', 'filters' => $filter, 'columns' => [$field],
    ]));

    $schemas = Mockery::mock(ViewSchemaProvider::class);
    $schemas->shouldReceive('schema')->with($actor, $viewScope->entityHandle)->andReturn(new ViewSchema('fixture/1', $viewScope->entityHandle, [
        new ViewField($field, 'string', ['eq'], true, true, true, true, $field),
    ]));
    $this->app->instance(ViewSchemaProvider::class, $schemas);
    $policy = Mockery::mock(ViewAccessPolicy::class);
    $policy->shouldReceive('allows')->andReturnUsing(fn ($context, $action, $subject): bool => $context === $actor && $subject->scope == $viewScope);
    $this->app->instance(ViewAccessPolicy::class, $policy);

    $db = DB::connection();
    $fieldOwner = new ModuleRef('axiomasoft/corex-custom-fields');
    $inventory = Mockery::mock(SavedViewInventory::class);
    $inventory->shouldReceive('connection')->twice()->withArgs(fn ($current): bool => $current == $scope)->andReturn($db);
    $inventory->shouldReceive('targets')->twice()->withArgs(fn ($current, $context, $operation, $id, $value): bool => $current == $scope
        && $context === $actor && $operation === 'save' && $id === null && $value === $input)->andReturn([$fieldOwner]);
    $this->app->instance(SavedViewInventory::class, $inventory);

    $repository = resolve(SavedViewRepository::class);
    $control = resolve(DatabaseScopeControl::class);
    $view = $db->transaction(function () use ($repository, $actor, $input, $control, $scope, $fieldOwner): SavedViewRef {
        $view = $repository->save($actor, $input);
        expect($control->outstandingReferences($scope, $fieldOwner))->toHaveCount(1);

        return $view;
    });
    $compiled = $repository->compile($actor, $view->id);
    expect($control->outstandingReferences($scope, $fieldOwner))->toBe([])
        ->and($db->table('sys_saved_views')->where('id', $view->id)->value('created_by'))->toBe('search-fixture-index')
        ->and($compiled->projection)->toBe([$field])
        ->and(JsonDocuments::array($compiled->filters))->toEqual($filter);

    $control->fenceModule($scope, $fieldOwner, 'close-field-owner', null);
    expect(fn () => $repository->save($actor, $input))->toThrow(LifecycleBlocked::class, 'Module reference admission is closed.')
        ->and(array_column($repository->viewsFor($actor, $viewScope), 'id'))->toBe([$view->id]);
})->group('pg');

it('refuses saved view writes for mismatched actors, accounts and workspaces before consulting inventory', function (): void {
    $tenant = resolve(TenantContextResolver::class)->current();
    $service = 'search-fixture-index';
    WorkScopeFixture::install($this->app, $service, 'service')->scope($tenant);
    $otherTenant = new TenantContext(new AccountRef('other-account', 'other', 'active', [], []));
    $actor = new ViewContext($tenant, 'system', $service);
    $repository = resolve(SavedViewRepository::class);
    $input = fn (?string $workspace): SaveViewInput => new SaveViewInput(
        null, '', new ViewScope('csp.document', $workspace, null, null),
        'table', ['en' => 'Pumps'], JsonDocuments::capture(['version' => 'saved-view/1']),
    );
    $mismatch = 'View write belongs to another current principal or scope.';

    foreach ([
        new ViewContext($tenant, 'system', 'other-service'),
        new ViewContext($tenant, 'system', null),
        new ViewContext($tenant, 'user', $service),
        new ViewContext($tenant, 'guest', null),
        new ViewContext($otherTenant, 'system', $service),
    ] as $wrongActor) {
        expect(fn () => $repository->save($wrongActor, $input(null)))->toThrow(LifecycleBlocked::class, $mismatch);
    }
    expect(fn () => $repository->save($actor, $input('W1')))->toThrow(LifecycleBlocked::class, $mismatch);

    foreach (['user', 'agent', 'api_key'] as $kind) {
        WorkScopeFixture::install($this->app, $service, $kind);
        expect(fn () => $repository->save($actor, $input(null)))->toThrow(LifecycleBlocked::class, $mismatch);
    }
    expect(DB::table('sys_saved_views')->count())->toBe(0);
})->group('pg');
