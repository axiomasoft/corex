<?php

declare(strict_types=1);

use CoreX\Operations\OperationsServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

trait RegistersOperationsForPackmanDiscovery
{
    protected function getPackageProviders($app): array
    {
        return [...parent::getPackageProviders($app), OperationsServiceProvider::class];
    }
}

uses(RegistersOperationsForPackmanDiscovery::class);

it('discovers and compiles installed CoreX modules through the actual Packman command', function (): void {
    $original = $this->app->basePath();
    $temporary = sys_get_temp_dir().'/corex-packman-integration-'.bin2hex(random_bytes(12));
    $files = new Filesystem;
    $files->makeDirectory($temporary.'/vendor/composer', 0700, true);
    $files->makeDirectory($temporary.'/bootstrap/cache', 0700, true);
    $installed = dirname(__DIR__, 2).'/vendor/composer/installed.json';
    $files->copy($installed, $temporary.'/vendor/composer/installed.json');
    $this->app->setBasePath($temporary);

    try {
        $discovered = resolve(ModuleDiscovery::class)->discover();
        $names = array_map(static fn ($manifest): string => $manifest->composerName, $discovered);
        expect($names)->toContain('axiomasoft/corex-tasks', 'flex/crm');
        expect(Artisan::call('modules:compile'))->toBe(0, Artisan::output());
        $this->app->forgetInstance(ModuleRegistry::class);
        $registry = resolve(ModuleRegistry::class);
        expect($registry->get('axiomasoft/corex-tasks')->composerName)->toBe('axiomasoft/corex-tasks')
            ->and(array_map(static fn ($entity): string => $entity->handle, $registry->compiled()->entities))
            ->toContain('tsk.task', 'crm.company');
    } finally {
        $this->app->setBasePath($original);
        $files->deleteDirectory($temporary);
    }
});
