<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\CoreServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Support\DefaultProviders;

/**
 * `php artisan config:cache` serialises the merged configuration with var_export into a PHP
 * file, so every value must survive that file being included: no closures, no objects other
 * than enum cases (CFG-004). Package configs are loaded the way config:cache loads them (a plain require).
 *
 * @return array<string, array<array-key, mixed>> config file name => array
 */
function configCacheDiscoverPackageConfigs(): array
{
    $configs = [];
    $files = glob(dirname(__DIR__, 2).'/packages/*/config/*.php') ?: [];
    sort($files);

    foreach ($files as $file) {
        $configs[basename($file, '.php')] = require $file;
    }

    return $configs;
}

/**
 * @param  array<array-key, mixed>  $config
 * @return array<array-key, mixed>
 */
function configCacheRoundTrip(array $config, string $label): array
{
    configCacheAssertSerialisable($config, $label);

    // config:cache writes exactly this shape: `<?php return var_export(...);` and the
    // application includes that file.
    $path = sys_get_temp_dir().'/corex-config-cache-'.bin2hex(random_bytes(8)).'.php';
    file_put_contents($path, '<?php return '.var_export($config, true).';');

    try {
        /** @var array<array-key, mixed> $loaded */
        $loaded = include $path;
    } finally {
        unlink($path);
    }

    return $loaded;
}

/** @param  array<array-key, mixed>  $config */
function configCacheAssertSerialisable(array $config, string $path): void
{
    foreach ($config as $key => $value) {
        $here = $path.'.'.$key;

        if ($value instanceof Closure) {
            throw new LogicException($here.' is a Closure: config:cache cannot serialise it');
        }

        if (is_object($value) && ! $value instanceof UnitEnum) {
            throw new LogicException($here.' is an instance of '.$value::class.': config:cache cannot serialise it');
        }

        if (is_resource($value)) {
            throw new LogicException($here.' is a resource: config:cache cannot serialise it');
        }

        if (is_array($value)) {
            configCacheAssertSerialisable($value, $here);
        }
    }
}

it('round-trips every package config file through var_export without loss', function (): void {
    $configs = configCacheDiscoverPackageConfigs();

    expect($configs)->not->toBeEmpty();

    foreach ($configs as $name => $config) {
        expect(configCacheRoundTrip($config, $name))->toBe($config, $name.' changed after var_export/eval');
    }
});

it('rejects a config that holds a closure or an object', function (): void {
    expect(fn () => configCacheRoundTrip(['probe' => ['handler' => static fn (): int => 1]], 'probe'))
        ->toThrow(LogicException::class, 'probe.probe.handler is a Closure')
        ->and(fn () => configCacheRoundTrip(['probe' => new stdClass], 'probe'))
        ->toThrow(LogicException::class, 'stdClass');
});

it('boots from a cached configuration repository instead of the config files', function (): void {
    $cached = $this->app['config']->all();

    foreach (configCacheDiscoverPackageConfigs() as $name => $config) {
        $cached[$name] = array_replace_recursive($config, $cached[$name] ?? []);
    }

    $cached['corex']['tables']['settings'] = 'sys_settings_from_cache';
    $cached['app']['providers'] = [...(new DefaultProviders)->toArray(), CoreServiceProvider::class];

    $base = sys_get_temp_dir().'/corex-config-cache-'.bin2hex(random_bytes(6));
    mkdir($base.'/bootstrap/cache', 0777, true);
    mkdir($base.'/storage/framework', 0777, true);
    file_put_contents(
        $base.'/bootstrap/cache/config.php',
        '<?php return '.var_export(configCacheRoundTrip($cached, 'cache'), true).';'.PHP_EOL,
    );

    try {
        $app = new Application($base);
        $app->singleton(ExceptionHandler::class, Handler::class);
        (new LoadConfiguration)->bootstrap($app);
        (new RegisterFacades)->bootstrap($app);
        (new RegisterProviders)->bootstrap($app);
        (new BootProviders)->bootstrap($app);

        expect($app->configurationIsCached())->toBeTrue()
            ->and($app->make(CoreOptions::class))->toBeInstanceOf(CoreOptions::class)
            ->and($app->make(CoreOptions::class)->settingsTable)->toBe($cached['corex']['tables']['settings']);
    } finally {
        (new Filesystem)->deleteDirectory($base);
    }
});
