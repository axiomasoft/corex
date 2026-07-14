<?php

declare(strict_types=1);

use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\TenantContext;
use CoreX\Tests\Stubs\StubCorexModule;
use CoreX\Tests\Stubs\Widget;
use Flex\Modules\Compiler\RegistryCompiler;
use Flex\Modules\Contracts\EntityRegistry;
use Flex\Modules\Contracts\ModuleStateReader;
use Flex\Modules\CorexModule;
use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Exceptions\ModuleNotFound;
use Flex\Modules\Extend\Entity;
use Flex\Modules\Extend\Menu;
use Flex\Modules\Extend\Permissions;
use Flex\Modules\Gate\AllowAllActivationGate;
use Flex\Modules\GateDecision;
use Flex\Modules\Manifest;
use Flex\Modules\ModulesServiceProvider;
use Flex\Modules\Registry\ArrayModuleStateReader;
use Flex\Modules\Registry\CompiledModule;
use Flex\Modules\Registry\CompiledModuleRegistry;
use Flex\Modules\Registry\FieldDefinition;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Stub module declaring three different extender slices (Entity, Permissions,
 * Menu) — exercises A10's exact gap: a manifest whose declarations must reach
 * the persisted registry and the runtime.
 */
final class P15CompiledStubModule extends CorexModule
{
    public function manifest(): Manifest
    {
        return Manifest::make('corex/p15-stub')
            ->title('P15 Stub')
            ->tablePrefix('pft')
            ->extends([
                Entity::make(Widget::class)
                    ->handle('pft.thing')
                    ->table('pft_things')
                    ->label('Thing', 'Things')
                    ->auditable()
                    ->fields([new FieldDefinition('name', 'string')]),
                Permissions::group('thing', ['view', 'create'], 'app'),
                Menu::item('app', 'pft.things')->label('Things')->icon('cube'),
            ]);
    }
}

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

it('flex:modules:compile persists the entity, permissions and menu slices, not just a counter (A10)', function (): void {
    $this->app->instance(
        ModuleDiscovery::class,
        new ModuleDiscovery(fallbackModules: [P15CompiledStubModule::class]),
    );

    expect(Artisan::call('flex:modules:compile'))->toBe(0);

    $cachePath = CompiledModuleRegistry::defaultCachePath();

    // File is a plain-array payload — no object was var_export'd into it.
    expect(file_get_contents($cachePath))->not->toContain('::__set_state');

    $registry = require $cachePath;

    expect($registry['registry']['entities'])->toHaveKey('pft.thing')
        ->and($registry['registry']['entities']['pft.thing']['model'])->toBe(Widget::class)
        ->and($registry['registry']['permissions'])->toHaveCount(1)
        ->and($registry['registry']['permissions'][0]['entity'])->toBe('thing')
        ->and($registry['registry']['menu'])->toHaveCount(1)
        ->and($registry['registry']['menu'][0]['handle'])->toBe('pft.things');

    unlink($cachePath);
});

it('projects the compiled entities into EntityRegistry at boot — runtime path, no explicit register()', function (): void {
    $this->app->instance(
        ModuleDiscovery::class,
        new ModuleDiscovery(fallbackModules: [P15CompiledStubModule::class]),
    );
    Artisan::call('flex:modules:compile');
    $cachePath = CompiledModuleRegistry::defaultCachePath();

    // Re-boot the provider so its app.booted projection reads the file we just
    // wrote (force re-register drops the stale singletons bound before it).
    $this->app->register(new ModulesServiceProvider($this->app), force: true);

    $registry = $this->app->make(EntityRegistry::class);

    expect($registry->has('pft.thing'))->toBeTrue()
        ->and($registry->get('pft.thing')->model)->toBe(Widget::class)
        ->and($registry->get('pft.thing')->labelPlural)->toBe('Things');

    unlink($cachePath);
});

it('adds the compiled entity handle to the morph map at boot, spanning the whole registry', function (): void {
    $this->app->instance(
        ModuleDiscovery::class,
        new ModuleDiscovery(fallbackModules: [P15CompiledStubModule::class]),
    );
    Artisan::call('flex:modules:compile');
    $cachePath = CompiledModuleRegistry::defaultCachePath();

    $this->app->register(new ModulesServiceProvider($this->app), force: true);

    expect(Relation::getMorphedModel('pft.thing'))->toBe(Widget::class);

    unlink($cachePath);
});

it('reads the persisted registry back through compiled()', function (): void {
    $path = moduleRegistryCachePath();
    $compiled = (new RegistryCompiler)->compile([(new P15CompiledStubModule)->manifest()]);
    CompiledModuleRegistry::writeAtomic($path, [], $compiled);

    $registry = new CompiledModuleRegistry($path, new ArrayModuleStateReader, Cache::store());
    $read = $registry->compiled();

    expect($read->entities)->toHaveKey('pft.thing')
        ->and($read->entities['pft.thing']->model)->toBe(Widget::class)
        ->and($read->permissions)->toHaveCount(1);

    unlink($path);
});

it('writeAtomic throws and leaves the destination intact when the atomic move fails', function (): void {
    // Destination is an existing directory — rename(tmpFile, dir) fails with
    // EISDIR, so the failed-rename branch is the one exercised.
    $destination = sys_get_temp_dir().'/flex_modules_reg_'.bin2hex(random_bytes(4));
    mkdir($destination);

    expect(fn () => CompiledModuleRegistry::writeAtomic(
        $destination,
        [new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], [])],
    ))->toThrow(RuntimeException::class);

    // The directory is untouched and no orphaned tmp file was left behind.
    expect(is_dir($destination))->toBeTrue()
        ->and(glob($destination.'.*.tmp'))->toBe([]);

    rmdir($destination);
});

it('throws in production-style strict mode when the compiled registry file is absent (fail-loud)', function (): void {
    $registry = new CompiledModuleRegistry(
        cachePath: moduleRegistryCachePath(),
        stateReader: new ArrayModuleStateReader,
        cache: Cache::store(),
        failIfMissing: true,
    );

    expect(fn () => $registry->all())->toThrow(RuntimeException::class, 'flex:modules:compile')
        ->and(fn () => $registry->compiled())->toThrow(RuntimeException::class, 'flex:modules:compile');
});

it('returns an empty registry outside production when the compiled file is absent', function (): void {
    $registry = new CompiledModuleRegistry(
        cachePath: moduleRegistryCachePath(),
        stateReader: new ArrayModuleStateReader,
        cache: Cache::store(),
        failIfMissing: false,
    );

    expect($registry->all())->toBe([])
        ->and($registry->compiled()->entities)->toBe([]);
});
