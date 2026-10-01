<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\CoreOptions;
use CoreX\Panels\SavedViews\Contracts\SavedViewRepository;
use CoreX\Panels\SavedViews\Contracts\ViewAccessPolicy;
use CoreX\Panels\SavedViews\Contracts\ViewSchemaProvider;
use CoreX\Panels\SavedViews\Internal\DenyViewAccessPolicy;
use CoreX\Panels\SavedViews\Internal\SqlSavedViewRepository;
use CoreX\Panels\SavedViews\Internal\UnavailableViewSchemaProvider;
use CoreX\Panels\SavedViews\Internal\ViewConfigValidator;
use Illuminate\Contracts\Foundation\Application;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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
