<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\TenantContext;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\CachePrefixBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\SessionConnectionBootstrapper;
use CoreX\Tenancy\Runtime\Internal\FrameworkConfig;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Config\Repository;

uses()->group('pg');

function contextTCI(string $account): TenantContext
{
    return new TenantContext(new AccountRef(id: $account, slug: 's', status: 'active', features: [], limits: []));
}

function databaseTCI(): DatabaseConnectionConfig
{
    return new DatabaseConnectionConfig('pgsql', 'h', 5432, 'd', 'u');
}

function cacheBootstrapperTCI(string $store): CachePrefixBootstrapper
{
    return new CachePrefixBootstrapper(resolve(CacheManager::class), resolve(FrameworkConfig::class), [$store], 'tenant_%tenant%_', 'pgsql');
}

it('isolates the file store per account — values and locks — and restores the exact store config', function (): void {
    $cache = resolve(CacheManager::class);
    $original = config('cache.stores.tenant_file');
    $bootstrapper = cacheBootstrapperTCI('tenant_file');

    $cache->store('tenant_file')->put('k', 'C', 60);
    $centralLock = $cache->store('tenant_file')->lock('k', 30);
    expect($centralLock->get())->toBeTrue();

    $bootstrapper->bootstrap(contextTCI('A'), databaseTCI());
    expect($cache->store('tenant_file')->get('k'))->toBeNull();
    $cache->store('tenant_file')->put('k', 'A', 60);
    $lockA = $cache->store('tenant_file')->lock('k', 30);
    expect($lockA->get())->toBeTrue();   // not blocked by the central lock of the same name
    $bootstrapper->revert();

    $bootstrapper->bootstrap(contextTCI('B'), databaseTCI());
    expect($cache->store('tenant_file')->get('k'))->toBeNull();
    $lockB = $cache->store('tenant_file')->lock('k', 30);
    expect($lockB->get())->toBeTrue();   // nor by A's
    $bootstrapper->revert();

    $bootstrapper->bootstrap(contextTCI('A'), databaseTCI());
    expect($cache->store('tenant_file')->get('k'))->toBe('A');
    $lockA->release();
    $bootstrapper->revert();

    expect($cache->store('tenant_file')->get('k'))->toBe('C')
        ->and(config('cache.stores.tenant_file'))->toBe($original);

    $centralLock->release();
});

it('prefixes redis, memcached and database stores with the account hash and restores them byte for byte', function (string $driver, array $store): void {
    config(['cache.stores.drv' => $store + ['driver' => $driver]]);
    $before = config('cache.stores.drv');
    $bootstrapper = cacheBootstrapperTCI('drv');

    $bootstrapper->bootstrap(contextTCI('A'), databaseTCI());
    $inA = config('cache.stores.drv');
    $bootstrapper->revert();
    $bootstrapper->bootstrap(contextTCI('B'), databaseTCI());
    $inB = config('cache.stores.drv');
    $bootstrapper->revert();

    expect($inA['prefix'])->toStartWith('tenant_'.hash('sha256', 'A').'_')
        ->and($inB['prefix'])->toStartWith('tenant_'.hash('sha256', 'B').'_')
        ->and($inA['prefix'])->not->toBe($inB['prefix'])
        ->and(config('cache.stores.drv'))->toBe($before);

    if ($driver === 'database') {
        // the table location stays central whatever the tenant default connection is
        expect($inA['connection'])->toBe('pgsql')->and($inA['lock_connection'])->toBe('pgsql');
    }
})->with([
    'redis' => ['redis', ['connection' => 'cache', 'lock_connection' => 'default']],
    'memcached' => ['memcached', ['servers' => [['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100]]]],
    'database' => ['database', ['table' => 'cache', 'lock_table' => 'cache_locks']],
]);

it('keeps an explicitly configured database cache connection', function (): void {
    config(['cache.stores.drv' => ['driver' => 'database', 'table' => 'cache', 'connection' => 'cache_db', 'lock_connection' => 'lock_db']]);
    $bootstrapper = cacheBootstrapperTCI('drv');

    $bootstrapper->bootstrap(contextTCI('A'), databaseTCI());
    expect(config('cache.stores.drv.connection'))->toBe('cache_db')
        ->and(config('cache.stores.drv.lock_connection'))->toBe('lock_db');
    $bootstrapper->revert();
});

it('rolls the store config back when a later store is unsupported (first mutation captured before the throwing one)', function (): void {
    config(['cache.stores.ok' => ['driver' => 'redis', 'connection' => 'cache'], 'cache.stores.bad' => ['driver' => 'array']]);
    $before = [config('cache.stores.ok'), config('cache.stores.bad')];
    $bootstrapper = new CachePrefixBootstrapper(resolve(CacheManager::class), resolve(FrameworkConfig::class), ['ok', 'bad'], 'tenant_%tenant%_', 'pgsql');

    expect(fn () => $bootstrapper->bootstrap(contextTCI('A'), databaseTCI()))->toThrow(InvalidConfiguration::class);
    $bootstrapper->revert();

    expect([config('cache.stores.ok'), config('cache.stores.bad')])->toBe($before);
});

it('clones the dedicated redis/memcached session store with an account prefix and restores everything', function (string $driver): void {
    config([
        'cache.stores.sess' => ['driver' => $driver, 'connection' => 'cache', 'servers' => [['host' => '127.0.0.1', 'port' => 11211, 'weight' => 100]]],
        'session.driver' => $driver,
        'session.store' => 'sess',
        'session.connection' => 'default',
        'session.prefix' => 'orig_',
    ]);
    $session = resolve(Repository::class)->get('session');
    $store = config('cache.stores.sess');
    $bootstrapper = new SessionConnectionBootstrapper(resolve('session'), resolve(CacheManager::class), app(), resolve(FrameworkConfig::class), 'pgsql', resolve('files'));

    $bootstrapper->bootstrap(contextTCI('A'), databaseTCI());
    $prefix = 'corex-session:'.hash('sha256', 'A').':';

    expect(config('cache.stores.sess.prefix'))->toStartWith($prefix);

    if ($driver === 'redis') {
        expect(config('session.prefix'))->toStartWith($prefix); // Laravel otherwise overrides the cache prefix
    }

    $bootstrapper->revert();

    expect(config('session'))->toBe($session)->and(config('cache.stores.sess'))->toBe($store);
})->with(['redis', 'memcached']);

it('rejects an unsupported session driver at bootstrap and restores the session config', function (): void {
    config(['session.driver' => 'cookie']);
    $before = config('session');
    $bootstrapper = new SessionConnectionBootstrapper(resolve('session'), resolve(CacheManager::class), app(), resolve(FrameworkConfig::class), 'pgsql', resolve('files'));

    expect(fn () => $bootstrapper->bootstrap(contextTCI('A'), databaseTCI()))
        ->toThrow(InvalidConfiguration::class, 'unsupported driver');
    $bootstrapper->revert();

    expect(config('session'))->toBe($before);
});

it('refuses file sessions that cannot be written and never leaves a half-prepared path', function (): void {
    $blocker = sys_get_temp_dir().'/corex-sess-blocker-'.bin2hex(random_bytes(6));
    file_put_contents($blocker, 'a file where the session root should be');
    config(['session.driver' => 'file', 'session.files' => $blocker]);
    $before = config('session');
    $bootstrapper = new SessionConnectionBootstrapper(resolve('session'), resolve(CacheManager::class), app(), resolve(FrameworkConfig::class), 'pgsql', resolve('files'));

    try {
        expect(fn () => $bootstrapper->bootstrap(contextTCI('A'), databaseTCI()))->toThrow(Exception::class);
        $bootstrapper->revert();
        expect(config('session'))->toBe($before);
    } finally {
        resolve('files')->delete($blocker);
    }
});
