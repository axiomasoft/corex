<?php

declare(strict_types=1);

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\LifecycleBlocker;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Data\ReferenceCursor;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Panels\Contracts\SavedViewInventory;
use CoreX\Panels\Laravel\SavedViewModuleReferences;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Str;
use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Data\Laravel\JsonDocuments;
use Packman\Panels\SavedViews\Contracts\SavedViewRepository;
use Packman\Panels\SavedViews\Data\SavedQuery;
use Packman\Panels\SavedViews\Data\SavedViewRef;
use Packman\Panels\SavedViews\Data\ViewContext;
use Packman\Panels\SavedViews\Data\ViewScope;

// The panels lifecycle scan decides which saved views block a module from
// being disabled. It must report only views that reference the module, flag
// views whose provenance cannot be compiled, and refuse cursors and
// directories that no longer describe the scope being scanned.

function savedViewScope(?string $workspace = 'ws-1'): DataScope
{
    return new DataScope('test', 'account-1', $workspace, 'resource', 1, 'incarnation', str_repeat('a', 64), 'user', 'operator');
}

function savedViewContext(string $account = 'account-1'): ViewContext
{
    $tenant = Mockery::mock(ContextScopeInterface::class);
    $tenant->shouldReceive('id')->andReturn($account);

    return new ViewContext($tenant, 'user', 'operator');
}

/** @param list<array{ViewContext,ViewScope}> $directory */
function savedViewReferences(array $directory, ?Closure $targets = null, ?SavedViewRepository $views = null): SavedViewModuleReferences
{
    $inventory = Mockery::mock(SavedViewInventory::class);
    $inventory->shouldReceive('scopes')->andReturn($directory);
    $inventory->shouldReceive('targets')->andReturnUsing($targets ?? static fn (): array => []);
    app()->instance(SavedViewInventory::class, $inventory);

    if ($views instanceof SavedViewRepository) {
        app()->instance(SavedViewRepository::class, $views);
    }

    return new SavedViewModuleReferences(app());
}

it('blocks only on views that reference the module and flags views it cannot compile', function (): void {
    $viewScope = new ViewScope('csp.document', 'ws-1', null, null);
    $context = savedViewContext();
    $views = Mockery::mock(SavedViewRepository::class);
    $views->shouldReceive('viewsFor')->andReturn([
        new SavedViewRef('referencing', $viewScope, 'table', 'r1', false),
        new SavedViewRef('unrelated', $viewScope, 'table', 'r1', false),
        new SavedViewRef('broken', $viewScope, 'table', 'r1', false),
    ]);
    $views->shouldReceive('compile')->andReturnUsing(static function (ViewContext $context, string $id): SavedQuery {
        if ($id === 'broken') {
            throw new RuntimeException('missing provenance');
        }

        return new SavedQuery('saved-view/1', JsonDocuments::capture([]), [], [], null);
    });
    $reported = [];
    $handler = Mockery::mock(ExceptionHandler::class);
    $handler->shouldReceive('report')->andReturnUsing(static function (Throwable $error) use (&$reported): void {
        $reported[] = $error->getMessage();
    });
    app()->instance(ExceptionHandler::class, $handler);
    $module = new ModuleRef('axiomasoft/corex-custom-fields');
    $references = savedViewReferences(
        [[$context, $viewScope]],
        static fn (DataScope $scope, ViewContext $context, string $operation, ?string $id): array => $id === 'referencing' ? [$module] : [new ModuleRef('axiomasoft/corex')],
        $views,
    );

    $page = $references->scan($module, savedViewScope(), null);

    expect(array_map(static fn (LifecycleBlocker $blocker): array => [$blocker->resourceId, $blocker->requiredAction], $page->blockers))->toBe([
        ['referencing', 'remove-or-rebind-saved-view'],
        ['broken', 'restore-saved-view-provenance'],
    ])->and($page->next)->toBeNull()
        ->and($reported)->toBe(['missing provenance']);
});

it('pages through the directory and refuses a cursor from another revision', function (): void {
    $first = new ViewScope('csp.document', 'ws-1', null, null);
    $second = new ViewScope('csp.task', 'ws-1', null, null);
    $views = Mockery::mock(SavedViewRepository::class);
    $views->shouldReceive('viewsFor')->andReturn([]);
    $references = savedViewReferences([[savedViewContext(), $first], [savedViewContext(), $second]], views: $views);
    $module = new ModuleRef('axiomasoft/corex-custom-fields');

    $page = $references->scan($module, savedViewScope(), null);

    expect($page->next)->toBeInstanceOf(ReferenceCursor::class)
        ->and($page->next?->position)->toBe('1')
        ->and($references->scan($module, savedViewScope(), $page->next)->next)->toBeNull()
        ->and(fn () => $references->scan($module, savedViewScope(), new ReferenceCursor('1', 'stale')))
        ->toThrow(LifecycleBlocked::class, 'Saved view directory changed.')
        ->and(fn () => $references->scan($module, savedViewScope(), new ReferenceCursor('-1', $page->revision)))
        ->toThrow(LifecycleBlocked::class, 'Saved view directory changed.');
});

it('refuses a directory entry that belongs to another account or workspace', function (string $account, string $workspace): void {
    $references = savedViewReferences([[savedViewContext($account), new ViewScope('csp.document', $workspace, null, null)]]);

    expect(fn () => $references->scan(new ModuleRef('axiomasoft/corex'), savedViewScope(), null))
        ->toThrow(LifecycleBlocked::class, 'Foreign saved view directory.');
})->with([
    'another account' => ['account-2', 'ws-1'],
    'another workspace' => ['account-1', 'ws-2'],
]);

it('refuses a view listed under a scope it does not carry', function (): void {
    $listed = new ViewScope('csp.document', 'ws-1', null, null);
    $views = Mockery::mock(SavedViewRepository::class);
    $views->shouldReceive('viewsFor')->andReturn([new SavedViewRef('moved', new ViewScope('csp.task', 'ws-1', null, null), 'table', 'r1', false)]);
    $references = savedViewReferences([[savedViewContext(), $listed]], views: $views);

    expect(fn () => $references->scan(new ModuleRef('axiomasoft/corex'), savedViewScope(), null))
        ->toThrow(LifecycleBlocked::class, 'Mismatched saved view scope.');
});

it('delegates leases to the inventory and fails closed without one', function (): void {
    $scope = savedViewScope();
    $lease = new OwnerLease('axiomasoft/packman-panels', 'saved-views', 'r1', 'token');
    $plan = new LifecyclePlanRef((string) Str::uuid7());
    $module = new ModuleRef('axiomasoft/corex');
    $inventory = Mockery::mock(SavedViewInventory::class);
    $inventory->shouldReceive('hold')->once()->with($scope, $module, $plan, null)->andReturn($lease);
    $inventory->shouldReceive('verify')->once()->with($scope, $lease);
    $inventory->shouldReceive('release')->once()->with($scope, $lease);
    $references = new SavedViewModuleReferences(app());

    expect(fn () => $references->hold($scope, $module, $plan, null))
        ->toThrow(LifecycleBlocked::class, 'Complete private and shared saved view inventory unavailable.');

    app()->instance(SavedViewInventory::class, $inventory);

    expect($references->owner())->toBe('axiomasoft/packman-panels')
        ->and($references->hold($scope, $module, $plan, null))->toBe($lease);
    $references->verify($scope, $lease);
    $references->release($scope, $lease);
});
