<?php

declare(strict_types=1);

namespace CoreX\ImportExport;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\ImportExport\Internal\ImportExportBindings;
use CoreX\ImportExport\Internal\ImportExportOptions;
use CoreX\ImportExport\Internal\ImportExportSchemaSets;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ImportExportServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([ImportExportSchemaSets::class], SchemaSetProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-import-export.php', 'corex-import-export');
        $this->app->singleton(ImportExportOptions::class, static fn (Application $app): ImportExportOptions => ImportExportOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-import-export', []),
        ));
        ImportExportBindings::register($this->app);
    }

    public function boot(): void
    {
        $this->app->make(ImportExportOptions::class);
    }
}
