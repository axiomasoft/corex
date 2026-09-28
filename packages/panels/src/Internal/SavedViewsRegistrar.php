<?php

declare(strict_types=1);

namespace CoreX\Panels\Internal;

use CoreX\Contracts\SavedViewRepository;
use CoreX\Contracts\ViewAccessPolicy;
use CoreX\Contracts\ViewSchemaProvider;
use CoreX\SavedViews\Internal\DenyViewAccessPolicy;
use CoreX\SavedViews\Internal\SqlSavedViewRepository;
use CoreX\SavedViews\Internal\UnavailableViewSchemaProvider;
use CoreX\SavedViews\Internal\ViewConfigValidator;
use CoreX\Support\CoreOptions;
use Illuminate\Contracts\Foundation\Application;

/** @internal Saved-view bindings composed by the owning panel provider. */
final class SavedViewsRegistrar
{
    public static function register(Application $app): void
    {
        $app->singleton(ViewSchemaProvider::class, UnavailableViewSchemaProvider::class);
        $app->singleton(ViewAccessPolicy::class, DenyViewAccessPolicy::class);
        $app->singleton(ViewConfigValidator::class);
        $app->singleton(SavedViewRepository::class, function (Application $app): SqlSavedViewRepository {
            $database = $app->make('db');

            return new SqlSavedViewRepository(
                schemas: $app->make(ViewSchemaProvider::class),
                policy: $app->make(ViewAccessPolicy::class),
                validator: $app->make(ViewConfigValidator::class),
                options: $app->make(CoreOptions::class),
                database: $database,
            );
        });
    }
}
