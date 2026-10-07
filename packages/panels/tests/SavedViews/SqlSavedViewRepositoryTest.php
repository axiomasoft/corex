<?php

declare(strict_types=1);

use CoreX\Config\Laravel\CoreOptionsParser;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Panels\SavedViews\Contracts\ViewAccessPolicy;
use CoreX\Panels\SavedViews\Contracts\ViewSchemaProvider;
use CoreX\Panels\SavedViews\Data\SaveViewInput;
use CoreX\Panels\SavedViews\Data\ViewAction;
use CoreX\Panels\SavedViews\Data\ViewContext;
use CoreX\Panels\SavedViews\Data\ViewField;
use CoreX\Panels\SavedViews\Data\ViewSchema;
use CoreX\Panels\SavedViews\Data\ViewScope;
use CoreX\Panels\SavedViews\Data\ViewSubject;
use CoreX\Panels\SavedViews\Internal\SqlSavedViewRepository;
use CoreX\Panels\SavedViews\Internal\ViewConfigValidator;
use CoreX\Time\Laravel\SystemClock;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;

beforeEach(function (): void {
    $this->migrateSchemaSets(['axiomasoft/corex-panels:tenant']);
});

it('persists only a validated AST and compiles it without SQL or identifiers', function (): void {
    $schema = new ViewSchema('neutral/1', 'lab.sample', [
        new ViewField('name', 'string', ['eq', 'ne', 'in', 'is_null'], true, true, true, true, 'name'),
    ]);
    $schemas = new readonly class($schema) implements ViewSchemaProvider
    {
        public function __construct(private ViewSchema $schema) {}

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
    $repository = new SqlSavedViewRepository($schemas, $policy, new ViewConfigValidator, CoreOptionsParser::fromArray([]), resolve('db'), new SystemClock);
    $context = new ViewContext(new TenantContext(new AccountRef('account', 'account', 'active', [], []), boxed: true), 'user', 'actor');
    $scope = new ViewScope('lab.sample', null, null, null);

    $view = $repository->save($context, new SaveViewInput(null, '', $scope, 'table', ['en' => 'Sample'], JsonDocuments::capture([
        'version' => 'saved-view/1', 'filters' => ['field' => 'name', 'op' => 'eq', 'value' => 'safe'], 'sorts' => [], 'columns' => ['name'], 'groupBy' => null, 'presentation' => [],
    ])));

    expect($view->revision)->toBe('1')
        ->and($view->isDefault)->toBeTrue()
        ->and(JsonDocuments::array($repository->compile($context, $view->id)->filters))->toBe(['field' => 'name', 'op' => 'eq', 'value' => 'safe']);
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
        ->and(CoreOptionsParser::fromArray(['views' => ['connection' => 'pinned']])->viewsConnection)->toBe('pinned');
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
    $schemas = new readonly class($schema) implements ViewSchemaProvider
    {
        public function __construct(private ViewSchema $schema) {}

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
    $options = CoreOptionsParser::fromArray($override === null ? [] : ['views' => ['connection' => $override]]);
    $repository = new SqlSavedViewRepository(
        schemas: $schemas,
        policy: $policy,
        validator: new ViewConfigValidator,
        options: $options,
        database: $manager,
        clock: new SystemClock,
    );

    return [
        $repository,
        $manager,
        new ViewContext(new TenantContext(new AccountRef('account', 'account', 'active', [], []), boxed: true), 'user', 'actor'),
        new ViewScope('lab.sample', null, null, null),
    ];
}

it('updates saved views optimistically and replaces deleted defaults', function (): void {
    $schema = new ViewSchema('neutral/1', 'lab.sample', [
        new ViewField('name', 'string', ['eq', 'ne', 'in', 'is_null'], true, true, true, true, 'name'),
    ]);
    $schemas = new readonly class($schema) implements ViewSchemaProvider
    {
        public function __construct(private ViewSchema $schema) {}

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
    $repository = new SqlSavedViewRepository($schemas, $policy, new ViewConfigValidator, CoreOptionsParser::fromArray([]), resolve('db'), new SystemClock);
    $context = new ViewContext(new TenantContext(new AccountRef('account', 'account', 'active', [], []), boxed: true), 'user', 'actor');
    $scope = new ViewScope('lab.sample', null, null, null);

    $config = JsonDocuments::capture(['version' => 'saved-view/1', 'filters' => null, 'sorts' => [], 'columns' => ['name'], 'groupBy' => null, 'presentation' => []]);
    $first = $repository->save($context, new SaveViewInput(null, '', $scope, 'table', ['en' => 'First'], $config));
    $second = $repository->save($context, new SaveViewInput(null, '', $scope, 'table', ['en' => 'Second'], $config));
    expect($repository->viewsFor($context, $scope))->toHaveCount(2)->and($second->isDefault)->toBeFalse();
    $updated = $repository->save($context, new SaveViewInput($first->id, '1', $scope, 'table', ['en' => 'Updated'], $config));
    expect($updated->revision)->toBe('2');
    expect(fn () => $repository->save($context, new SaveViewInput($first->id, '1', $scope, 'table', ['en' => 'Stale'], $config)))
        ->toThrow(RuntimeException::class, 'revision conflict');
    expect($repository->setDefault($context, $second->id)->isDefault)->toBeTrue();
    $repository->delete($context, $second->id);
    $rows = $repository->viewsFor($context, $scope);
    expect($rows)->toHaveCount(1)->and($rows[0]->isDefault)->toBeTrue();
    $repository->delete($context, $second->id);
    $repository->delete($context, $first->id);
    expect($repository->viewsFor($context, $scope))->toBe([]);
    expect(fn () => $repository->setDefault($context, $first->id))->toThrow(RuntimeException::class, 'not found');
    expect(fn () => $repository->compile($context, $first->id))->toThrow(RuntimeException::class, 'not found');
});
