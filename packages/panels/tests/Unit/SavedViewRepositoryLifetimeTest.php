<?php

declare(strict_types=1);

use CoreX\Panels\Laravel\FencedSavedViewRepository;
use CoreX\Panels\PanelsServiceProvider;
use CoreX\Time\Laravel\SystemClock;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Foundation\Application;
use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Panels\Laravel\PanelsServiceProvider as NativePanels;
use Packman\Panels\SavedViews\Contracts\SavedViewRepository;
use Packman\Panels\SavedViews\Data\SavedViewRef;
use Packman\Panels\SavedViews\Data\ViewContext;
use Packman\Panels\SavedViews\Data\ViewScope;
use Packman\Panels\SavedViews\Laravel\SavedViewRepositoryFactory;
use Psr\Clock\ClockInterface;

final class SavedViewRefreshObserver
{
    /** @var list<SavedViewRepository> */
    public array $received = [];

    public function accept(SavedViewRepository $repository): void
    {
        $this->received[] = $repository;
    }
}

function savedViewLifetimeApp(): Application
{
    $app = new Application;
    $app->instance('config', new Repository);
    $app->instance(ClockInterface::class, new SystemClock);
    $app->register(DatabaseServiceProvider::class);
    $app->register(NativePanels::class);

    return $app;
}

it('extends the native scoped factory across resets and preserves its per-scope objects', function (bool $warm, bool $integrated): void {
    $app = savedViewLifetimeApp();
    $built = [];
    $app->scoped('host.views', function (Application $app) use (&$built): SavedViewRepository {
        return $built[] = (new SavedViewRepositoryFactory)->make($app);
    });
    $app->alias('host.views', SavedViewRepository::class);
    $app->alias(SavedViewRepository::class, 'views.reader');
    $before = $warm ? $app->make('views.reader') : null;
    $observed = [];

    if ($integrated) {
        $app->register(PanelsServiceProvider::class);
    } else {
        $app->extend(SavedViewRepository::class, function (SavedViewRepository $host) use (&$observed): SavedViewRepository {
            $observed[] = $host;

            return $host;
        });
    }
    expect($built)->toHaveCount($warm ? 1 : 0);
    $app->boot();
    $first = $app->make('views.reader');
    expect($app->make(SavedViewRepository::class))->toBe($first)->and($built)->toHaveCount(1);

    if ($warm) {
        expect($built[0])->toBe($before);
    }
    $app->forgetScopedInstances();
    $second = $app->make(SavedViewRepository::class);
    expect($app->make('host.views'))->toBe($second)->and($app->make('views.reader'))->toBe($second)
        ->and($second)->not->toBe($first)->and($built)->toHaveCount(2)->and($built[0])->not->toBe($built[1]);

    if ($integrated) {
        $inner = new ReflectionProperty(FencedSavedViewRepository::class, 'inner');
        expect($first)->toBeInstanceOf(FencedSavedViewRepository::class)->and($second)->toBeInstanceOf(FencedSavedViewRepository::class);
        expect($inner->getValue($first))->toBe($built[0])->and($inner->getValue($second))->toBe($built[1]);
    } else {
        expect([$first, $second])->toBe($built)->and($observed)->toBe($warm ? [$built[0]] : $built);
    }
})->with(['cold' => false, 'warm' => true])->with(['Laravel identity extender' => false, 'CoreX Panels' => true]);

it('keeps the supplied mutable host and refreshes its observer without calling the dormant factory', function (): void {
    $app = savedViewLifetimeApp();
    $app->singleton(SavedViewRepository::class, static fn () => throw new LogicException('Dormant repository factory ran.'));
    $host = Mockery::mock(SavedViewRepository::class);
    $app->instance(SavedViewRepository::class, $host);
    $observer = new SavedViewRefreshObserver;
    expect($app->refresh(SavedViewRepository::class, $observer, 'accept'))->toBe($host);
    $app->register(PanelsServiceProvider::class);
    $first = $app->make(SavedViewRepository::class);
    $app->forgetScopedInstances();
    expect($first)->toBeInstanceOf(FencedSavedViewRepository::class)
        ->and($app->make(SavedViewRepository::class))->toBe($first)->and($observer->received)->toBe([$first]);

    $context = new ViewContext(Mockery::mock(ContextScopeInterface::class), 'user', 'user-a');
    $scope = new ViewScope('sample.note', null, null, null);
    $view = new SavedViewRef('mutated-host-state', $scope, 'table', '2', false);
    $views = new ArrayObject;
    $host->expects('viewsFor')->twice()->with($context, $scope)->andReturnUsing(static fn (): array => $views->getArrayCopy());
    expect($first->viewsFor($context, $scope))->toBe([]);
    $views->append($view);
    expect($first->viewsFor($context, $scope))->toBe([$view]);
});

it('preserves independent transient refresh and caller identities on a later canonical alias', function (bool $integrated): void {
    $app = savedViewLifetimeApp();

    if ($integrated) {
        $app->register(PanelsServiceProvider::class);
    }
    $built = [];
    $app->bind('host.transient', function (Application $app) use (&$built): SavedViewRepository {
        return $built[] = (new SavedViewRepositoryFactory)->make($app);
    });
    $observer = new SavedViewRefreshObserver;
    $original = $app->refresh('host.transient', $observer, 'accept');
    $app->alias('host.transient', SavedViewRepository::class);

    if (! $integrated) {
        $app->extend(SavedViewRepository::class, static fn (SavedViewRepository $host): SavedViewRepository => $host);
    }
    $one = $app->make(SavedViewRepository::class);
    $app->forgetScopedInstances();
    $two = $app->make('host.transient');
    expect($built)->toHaveCount(4)->and($built[0])->toBe($original)->and($observer->received)->toHaveCount(1);
    $public = [$observer->received[0], $one, $two];

    if ($integrated) {
        $inner = new ReflectionProperty(FencedSavedViewRepository::class, 'inner');
        foreach ($public as $index => $repository) {
            expect($repository)->toBeInstanceOf(FencedSavedViewRepository::class);
            expect($inner->getValue($repository))->toBe($built[$index + 1]);
        }
    } else {
        expect($public)->toBe(array_slice($built, 1));
    }
})->with(['Laravel identity extender' => false, 'CoreX Panels' => true]);
