<?php

declare(strict_types=1);

namespace CoreX\Panels;

use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Panels\Laravel\FencedSavedViewRepository;
use CoreX\Panels\Laravel\SavedViewModuleReferences;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Panels\Laravel\PanelsServiceProvider as PackmanPanelsServiceProvider;
use Packman\Panels\SavedViews\Contracts\SavedViewRepository;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PanelsServiceProvider extends ServiceProvider
{
    /** @var array<string,true> Canonical bindings with an extender on the factory. */
    private array $extendedFactories = [];

    private bool $attaching = false;

    #[Override]
    public function register(): void
    {
        $this->app->register(PackmanPanelsServiceProvider::class);
        $this->app->tag(SavedViewModuleReferences::class, ModuleReferences::class);

        if ($this->app instanceof Application) {
            $binding = $this->app->getAlias(SavedViewRepository::class);

            if (! $this->app->isShared($binding) || ! $this->app->resolved($binding)) {
                $this->extendedFactories[$binding] = true;
            }
            $this->app->beforeResolving($this->prepareRepository(...));
        }

        $this->app->extend(SavedViewRepository::class, $this->decorate(...));
    }

    /** @param array<array-key,mixed> $parameters */
    private function prepareRepository(string $binding, array $parameters, Application $app): void
    {
        if ($this->attaching || $binding !== $app->getAlias(SavedViewRepository::class)) {
            return;
        }

        if (! $app->isShared($binding) || ! $app->resolved($binding)) {
            if (! isset($this->extendedFactories[$binding])) {
                $this->extendedFactories[$binding] = true;
                $app->extend($binding, $this->decorate(...));
            }

            return;
        }

        // A warm extend changes only the occupied cache slot. Resolve the current
        // host lazily, keeping that slot occupied during native refresh callbacks.
        $this->attaching = true;

        try {
            if (! $app->make($binding) instanceof FencedSavedViewRepository) {
                $app->extend($binding, $this->decorate(...));
            }
        } finally {
            $this->attaching = false;
        }
    }

    private function decorate(SavedViewRepository $host): FencedSavedViewRepository
    {
        return $host instanceof FencedSavedViewRepository ? $host : new FencedSavedViewRepository($host, $this->app);
    }
}
