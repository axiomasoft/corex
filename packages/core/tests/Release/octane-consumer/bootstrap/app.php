<?php

declare(strict_types=1);

use CoreX\CoreServiceProvider;
use Illuminate\Foundation\Application;
use Laravel\Octane\OctaneServiceProvider;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withProviders([CoreServiceProvider::class, OctaneServiceProvider::class, LifecycleServiceProvider::class])
    ->withExceptions()
    ->create();

$storage = getenv('LARAVEL_STORAGE_PATH');

if (! is_string($storage) || ! str_starts_with($storage, sys_get_temp_dir().'/corex-octane-test-')) {
    throw new RuntimeException('Octane fixture requires isolated test storage before boot.');
}

$app->useStoragePath($storage);

return $app;
