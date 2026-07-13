<?php

declare(strict_types=1);

use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use CoreX\Tests\Stubs\StubCorexModule;
use Flex\Modules\Contracts\ModuleStateReader;
use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Exceptions\ModuleNotFound;
use Flex\Modules\Gate\AllowAllActivationGate;
use Flex\Modules\GateDecision;
use Flex\Modules\ModulesServiceProvider;
use Flex\Modules\Registry\ArrayModuleStateReader;
use Flex\Modules\Registry\CompiledModule;
use Flex\Modules\Registry\CompiledModuleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    $this->app->register(ModulesServiceProvider::class);
});

function moduleRegistryCtx(string $accountId): TenantContext
{
    return new TenantContext(new AccountRef(id: $accountId, slug: $accountId, status: 'active', features: [], limits: []));
}

function moduleRegistryCachePath(): string
{
    return sys_get_temp_dir().'/flex_modules_test_'.bin2hex(random_bytes(4)).'.php';
}

it('reads all() and get() from the compiled file', function (): void {
    $path = moduleRegistryCachePath();
    $alpha = new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], []);
    $beta = new CompiledModule('corex/beta', 'Beta', 'bet', [], [], []);
    CompiledModuleRegistry::writeAtomic($path, [$alpha, $beta]);

    $registry = new CompiledModuleRegistry($path, new ArrayModuleStateReader, Cache::store());

    expect($registry->all())->toHaveCount(2)
        ->and($registry->get('corex/alpha')->title)->toBe('Alpha')
        ->and($registry->get('corex/beta')->tablePrefix)->toBe('bet');

    unlink($path);
});

it('throws ModuleNotFound for an unknown module', function (): void {
    $path = moduleRegistryCachePath();
    CompiledModuleRegistry::writeAtomic($path, []);

    $registry = new CompiledModuleRegistry($path, new ArrayModuleStateReader, Cache::store());

    expect(fn () => $registry->get('corex/missing'))
        ->toThrow(ModuleNotFound::class);

    unlink($path);
});

it('active() intersects the compiled registry with the enabled set from the reader', function (): void {
    $path = moduleRegistryCachePath();
    CompiledModuleRegistry::writeAtomic($path, [
        new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], []),
        new CompiledModule('corex/beta', 'Beta', 'bet', [], [], []),
    ]);

    $reader = new ArrayModuleStateReader([
        'acc-1' => ['corex/alpha' => ['state' => 'enabled', 'edition' => 'standard']],
    ]);
    $registry = new CompiledModuleRegistry($path, $reader, Cache::store());

    $active = $registry->active(moduleRegistryCtx('acc-1'));

    expect($active)->toHaveCount(1)
        ->and($active[0]->composerName)->toBe('corex/alpha')
        ->and($registry->isActive('corex/alpha', moduleRegistryCtx('acc-1')))->toBeTrue()
        ->and($registry->isActive('corex/beta', moduleRegistryCtx('acc-1')))->toBeFalse();

    unlink($path);
});

it('isolates active() per tenant context — no state leaks across accounts', function (): void {
    $path = moduleRegistryCachePath();
    CompiledModuleRegistry::writeAtomic($path, [
        new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], []),
        new CompiledModule('corex/beta', 'Beta', 'bet', [], [], []),
    ]);

    $reader = new ArrayModuleStateReader([
        'acc-1' => ['corex/alpha' => ['state' => 'enabled', 'edition' => 'standard']],
        'acc-2' => ['corex/beta' => ['state' => 'enabled', 'edition' => 'standard']],
    ]);
    $registry = new CompiledModuleRegistry($path, $reader, Cache::store());

    $namesFor = static fn (array $modules): array => array_map(static fn (CompiledModule $m): string => $m->composerName, $modules);

    expect($namesFor($registry->active(moduleRegistryCtx('acc-1'))))->toBe(['corex/alpha'])
        ->and($namesFor($registry->active(moduleRegistryCtx('acc-2'))))->toBe(['corex/beta']);

    unlink($path);
});

it('does not call the reader again on a warm cache', function (): void {
    $path = moduleRegistryCachePath();
    CompiledModuleRegistry::writeAtomic($path, [new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], [])]);

    $reader = new class implements ModuleStateReader
    {
        public int $calls = 0;

        public function enabledFor(TenantContext $ctx): array
        {
            $this->calls++;

            return ['corex/alpha' => ['state' => 'enabled', 'edition' => 'standard']];
        }
    };

    $registry = new CompiledModuleRegistry($path, $reader, Cache::store());
    $ctx = moduleRegistryCtx('acc-1');

    $registry->active($ctx);
    $registry->active($ctx);
    $registry->isActive('corex/alpha', $ctx);

    expect($reader->calls)->toBe(1);

    unlink($path);
});

it('AllowAllActivationGate::check() always allows', function (): void {
    $decision = (new AllowAllActivationGate)->check(moduleRegistryCtx('acc-1'), 'corex/alpha', 'standard');

    expect($decision)->toBeInstanceOf(GateDecision::class)
        ->and($decision->allowed)->toBeTrue()
        ->and($decision->reason)->toBeNull();
});

it('writes the compiled registry atomically, leaving no stray tmp file', function (): void {
    $path = moduleRegistryCachePath();
    CompiledModuleRegistry::writeAtomic($path, [new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], [])]);

    expect(is_file($path))->toBeTrue()
        ->and(glob(dirname($path).'/*.tmp'))->toBe([]);

    $data = require $path;
    expect($data['modules'][0]['composerName'])->toBe('corex/alpha');

    unlink($path);
});

it('flex:modules:compile writes the compiled registry file for the discovered modules', function (): void {
    $this->app->instance(
        ModuleDiscovery::class,
        new ModuleDiscovery(fallbackModules: [StubCorexModule::class]),
    );

    $exitCode = Artisan::call('flex:modules:compile');

    expect($exitCode)->toBe(0);

    $cachePath = CompiledModuleRegistry::defaultCachePath();
    $data = require $cachePath;

    expect($data['modules'])->toHaveCount(1)
        ->and($data['modules'][0]['composerName'])->toBe('corex/stub-module')
        ->and($data['modules'][0]['tablePrefix'])->toBe('stb');

    unlink($cachePath);
});
