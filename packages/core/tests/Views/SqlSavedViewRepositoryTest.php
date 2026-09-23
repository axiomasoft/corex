<?php

declare(strict_types=1);

use CoreX\Contracts\ViewAccessPolicy;
use CoreX\Contracts\ViewSchemaProvider;
use CoreX\Support\CoreOptions;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use CoreX\Views\Data\SaveViewInput;
use CoreX\Views\Data\ViewAction;
use CoreX\Views\Data\ViewContext;
use CoreX\Views\Data\ViewField;
use CoreX\Views\Data\ViewSchema;
use CoreX\Views\Data\ViewScope;
use CoreX\Views\Data\ViewSubject;
use CoreX\Views\Internal\SqlSavedViewRepository;
use CoreX\Views\Internal\ViewConfigValidator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('sys_saved_view_scopes', function (Blueprint $table): void {
        $table->string('entity_code');
        $table->string('scope_key');
        $table->primary(['entity_code', 'scope_key']);
    });
    Schema::create('sys_saved_views', function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('entity_code');
        $table->string('workspace_id')->nullable();
        $table->string('owner_kind')->nullable();
        $table->string('owner_id')->nullable();
        $table->string('scope_key');
        $table->string('kind');
        $table->json('name');
        $table->json('config');
        $table->string('schema_version');
        $table->unsignedBigInteger('revision');
        $table->boolean('is_default');
        $table->string('default_scope_key')->nullable();
        $table->integer('position');
        $table->string('created_by');
        $table->timestamps();
        $table->unique(['entity_code', 'default_scope_key']);
    });
});

it('persists only a validated AST and compiles it without SQL or identifiers', function (): void {
    $schema = new ViewSchema('neutral/1', 'lab.sample', [
        new ViewField('name', 'string', ['eq', 'ne', 'in', 'is_null'], true, true, true, true, 'name'),
    ]);
    $schemas = new class($schema) implements ViewSchemaProvider
    {
        public function __construct(private readonly ViewSchema $schema) {}

        public function schema(ViewContext $context, string $entityHandle): ViewSchema
        {
            return $this->schema;
        }
    };
    $policy = new class implements ViewAccessPolicy
    {
        public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool
        {
            return true;
        }
    };
    $repository = new SqlSavedViewRepository($schemas, $policy, new ViewConfigValidator);
    $context = new ViewContext(new TenantContext(new AccountRef('account', 'account', 'active', [], []), boxed: true), 'user', 'actor');
    $scope = new ViewScope('lab.sample', null, null, null);

    $view = $repository->save($context, new SaveViewInput(null, '', $scope, 'table', ['en' => 'Sample'], [
        'version' => 'saved-view/1', 'filters' => ['field' => 'name', 'op' => 'eq', 'value' => 'safe'], 'sorts' => [], 'columns' => ['name'], 'groupBy' => null, 'presentation' => [],
    ]));

    expect($view->revision)->toBe('1')
        ->and($view->isDefault)->toBeTrue()
        ->and($repository->compile($context, $view->id)->filters)->toBe(['field' => 'name', 'op' => 'eq', 'value' => 'safe']);
});

it('resolves the current default on one repository across central, tenant, and restored central', function (): void {
    [$repository, $manager, $context, $scope] = corexSavedViewConnectionProbe($this->app);

    foreach (['central', 'tenant', 'central'] as $default) {
        $manager->current = $default;

        expect($repository->viewsFor($context, $scope))->toBe([]);
    }

    expect($manager->selected)->toBe(['central', 'tenant', 'central']);
});

it('keeps an explicit views connection fixed while the default changes', function (): void {
    [$repository, $manager, $context, $scope] = corexSavedViewConnectionProbe($this->app, 'pinned');

    foreach (['central', 'tenant', 'central'] as $default) {
        $manager->current = $default;

        expect($repository->viewsFor($context, $scope))->toBe([]);
    }

    expect($manager->selected)->toBe(['pinned', 'pinned', 'pinned'])
        ->and(CoreOptions::fromArray(['views' => ['connection' => 'pinned']])->viewsConnection)->toBe('pinned');
});

/**
 * @return array{0: SqlSavedViewRepository, 1: DatabaseManager, 2: ViewContext, 3: ViewScope}
 */
function corexSavedViewConnectionProbe(Application $app, ?string $override = null): array
{
    foreach (['central', 'tenant', 'pinned'] as $name) {
        $app['config']->set('database.connections.'.$name, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    $manager = new class($app, $app->make('db.factory')) extends DatabaseManager
    {
        public string $current = 'central';

        /** @var list<string> */
        public array $selected = [];

        public function getDefaultConnection()
        {
            return $this->current;
        }

        public function connection($name = null)
        {
            $resolved = is_string($name) && $name !== '' ? $name : $this->current;
            $this->selected[] = $resolved;

            return parent::connection($resolved);
        }
    };

    foreach (['central', 'tenant', 'pinned'] as $name) {
        $manager->connection($name)->getSchemaBuilder()->create('sys_saved_views', function (Blueprint $table): void {
            $table->string('entity_code');
            $table->string('scope_key');
            $table->integer('position');
        });
    }

    $manager->selected = [];

    $schema = new ViewSchema('neutral/1', 'lab.sample', [
        new ViewField('name', 'string', ['eq', 'ne', 'in', 'is_null'], true, true, true, true, 'name'),
    ]);
    $schemas = new class($schema) implements ViewSchemaProvider
    {
        public function __construct(private readonly ViewSchema $schema) {}

        public function schema(ViewContext $context, string $entityHandle): ViewSchema
        {
            return $this->schema;
        }
    };
    $policy = new class implements ViewAccessPolicy
    {
        public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool
        {
            return true;
        }
    };
    $options = CoreOptions::fromArray($override === null ? [] : ['views' => ['connection' => $override]]);
    $repository = new SqlSavedViewRepository(
        schemas: $schemas,
        policy: $policy,
        validator: new ViewConfigValidator,
        options: $options,
        database: $manager,
    );

    return [
        $repository,
        $manager,
        new ViewContext(new TenantContext(new AccountRef('account', 'account', 'active', [], []), boxed: true), 'user', 'actor'),
        new ViewScope('lab.sample', null, null, null),
    ];
}
