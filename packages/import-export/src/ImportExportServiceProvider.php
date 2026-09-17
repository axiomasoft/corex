<?php

declare(strict_types=1);

namespace CoreX\ImportExport;

use Illuminate\Support\ServiceProvider;
use Override;

final class ImportExportServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-import-export.php', 'corex-import-export');
    }
}
