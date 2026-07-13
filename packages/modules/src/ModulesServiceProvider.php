<?php

declare(strict_types=1);

namespace Flex\Modules;

use Flex\Modules\Contracts\EntityRegistry;
use Flex\Modules\Registry\ArrayEntityRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Override;

final class ModulesServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        // Bind the concrete singleton first so module providers can resolve
        // it directly to call register() (not part of the read-only contract),
        // then alias the contract to the same instance.
        $this->app->singleton(ArrayEntityRegistry::class);
        $this->app->singleton(
            EntityRegistry::class,
            static fn (Application $app): ArrayEntityRegistry => $app->make(ArrayEntityRegistry::class),
        );
    }

    public function boot(): void
    {
        // Built after every module provider has booted and registered its
        // entities (ADR-003: EntityRegistry is the single morph-map source).
        $this->app->booted(function (): void {
            $map = [];

            foreach ($this->app->make(EntityRegistry::class)->all() as $definition) {
                $map[$definition->handle] = $definition->model;
            }

            if ($map !== []) {
                Relation::morphMap($map);
            }
        });
    }
}
