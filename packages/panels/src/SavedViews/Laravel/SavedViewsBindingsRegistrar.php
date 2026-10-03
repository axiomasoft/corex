<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Panels\SavedViews\Contracts\SavedViewRepository;
use CoreX\Panels\SavedViews\Contracts\ViewAccessPolicy;
use CoreX\Panels\SavedViews\Contracts\ViewSchemaProvider;
use CoreX\Panels\SavedViews\Internal\DenyViewAccessPolicy;
use CoreX\Panels\SavedViews\Internal\SqlSavedViewRepository;
use CoreX\Panels\SavedViews\Internal\UnavailableViewSchemaProvider;
use CoreX\Panels\SavedViews\Internal\ViewConfigValidator;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** Saved-view bindings of the panels provider. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SavedViewsBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(ViewSchemaProvider::class, UnavailableViewSchemaProvider::class);
        $app->singleton(ViewAccessPolicy::class, DenyViewAccessPolicy::class);
        $app->singleton(ViewConfigValidator::class);
        $app->singleton(SavedViewRepository::class, static fn (Application $app): SqlSavedViewRepository => (new SavedViewRepositoryFactory)->make($app));
    }

    #[Override]
    public function boot(Application $app): void {}
}
