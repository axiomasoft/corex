<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\CoreOptions;
use CoreX\Panels\SavedViews\Contracts\ViewAccessPolicy;
use CoreX\Panels\SavedViews\Contracts\ViewSchemaProvider;
use CoreX\Panels\SavedViews\Internal\SqlSavedViewRepository;
use CoreX\Panels\SavedViews\Internal\ViewConfigValidator;
use Illuminate\Contracts\Foundation\Application;

/** Builds the saved-view repository on the database manager of the current application. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SavedViewRepositoryFactory
{
    public function make(Application $app): SqlSavedViewRepository
    {
        $database = $app->make('db');

        return new SqlSavedViewRepository(
            schemas: $app->make(ViewSchemaProvider::class),
            policy: $app->make(ViewAccessPolicy::class),
            validator: $app->make(ViewConfigValidator::class),
            options: $app->make(CoreOptions::class),
            database: $database,
        );
    }
}
