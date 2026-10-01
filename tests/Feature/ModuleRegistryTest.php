<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Contracts\ModuleDefinition;
use CoreX\Modules\Contracts\ModuleRegistryCache;
use CoreX\Modules\Contracts\ModuleStateReader;
use CoreX\Modules\Data\CompiledModule;
use CoreX\Modules\Data\Entity;
use CoreX\Modules\Data\FieldDefinition;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Data\Permissions;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Exceptions\ModuleNotFound;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Modules\Internal\Discovery\ModuleDiscovery;
use CoreX\Modules\Internal\Extend\Menu;
use CoreX\Modules\Internal\Gate\AllowAllActivationGate;
use CoreX\Modules\Internal\Gate\ModuleActivationDecision;
use CoreX\Modules\Internal\Registry\ArrayModuleStateReader;
use CoreX\Modules\Internal\Registry\CompiledModuleRegistry;
use CoreX\Modules\Internal\Registry\RepositoryModuleRegistryCache;
use CoreX\Modules\Internal\Support\ModulesOptions;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Tests\Stubs\StubModuleDefinition;
use CoreX\Tests\Stubs\Widget;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Stub module declaring three different extender slices (Entity, Permissions,
 * Menu) — exercises A10's exact gap: a manifest whose declarations must reach
 * the persisted registry and the runtime.
 */
final class P15CompiledStubModule extends ModuleDefinition
{
    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('corex/p15-stub')
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
    return sys_get_temp_dir().'/corex_modules_test_'.bin2hex(random_bytes(4)).'.php';
}

/**
 * Build a CompiledModuleRegistry with the P1.16 collaborators: a
 * tag-free {@see ModuleRegistryCache} (default store unless one is injected)
 * and a constant epoch resolver (0) — tests that exercise real epoch bumping
 * live in the pg-lane ModuleLifecycleTest.
 */
function newModuleRegistry(
    string $path,
    ModuleStateReader $reader,
    ?ModuleRegistryCache $cache = null,
    ?Closure $versionResolver = null,
    bool $failIfMissing = false,
): CompiledModuleRegistry {
    return new CompiledModuleRegistry(
        cachePath: $path,
        stateReader: $reader,
        cache: $cache ?? new RepositoryModuleRegistryCache(
            Cache::store(),
            ModulesOptions::fromArray(['cache' => ['ttl' => 3600, 'prefix' => 'corex.modules']]),
        ),
        versionResolver: $versionResolver ?? static fn (TenantContext $ctx): int => 0,
        failIfMissing: $failIfMissing,
    );
}

it('reads all() and get() from the compiled file', function (): void {
    $path = moduleRegistryCachePath();
    $alpha = new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], []);
    $beta = new CompiledModule('corex/beta', 'Beta', 'bet', [], [], []);
    CompiledModuleRegistry::writeAtomic($path, [$alpha, $beta]);

    $registry = newModuleRegistry($path, new ArrayModuleStateReader);

    expect($registry->all())->toHaveCount(2)
        ->and($registry->get('corex/alpha')->title)->toBe('Alpha')
        ->and($registry->get('corex/beta')->tablePrefix)->toBe('bet');

    unlink($path);
});

it('throws ModuleNotFound for an unknown module', function (): void {
    $path = moduleRegistryCachePath();
    CompiledModuleRegistry::writeAtomic($path, []);

    $registry = newModuleRegistry($path, new ArrayModuleStateReader);

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
    $registry = newModuleRegistry($path, $reader);

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
    $registry = newModuleRegistry($path, $reader);

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

    $registry = newModuleRegistry($path, $reader);
    $ctx = moduleRegistryCtx('acc-1');

    $registry->active($ctx);
    $registry->active($ctx);
    $registry->isActive('corex/alpha', $ctx);

    expect($reader->calls)->toBe(1);

    unlink($path);
});

it('AllowAllActivationGate::check() always allows', function (): void {
    $decision = (new AllowAllActivationGate)->check(moduleRegistryCtx('acc-1'), 'corex/alpha', 'standard');

    expect($decision)->toBeInstanceOf(ModuleActivationDecision::class)
        ->and($decision->allowed)->toBeTrue()
        ->and($decision->reason)->toBeNull();
});

it('writes the compiled registry atomically, leaving no stray tmp file', function (): void {
    $path = moduleRegistryCachePath();
    CompiledModuleRegistry::writeAtomic($path, [new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], [])]);

    // Glob only THIS write's own tmp siblings ($path.'.<rand>.tmp'), not the
    // whole system temp dir — otherwise the assertion depends on unrelated
    // machine state (A47/A48).
    expect(is_file($path))->toBeTrue()
        ->and(glob($path.'.*.tmp'))->toBe([]);

    $data = require $path;
    expect($data['modules'][0]['composerName'])->toBe('corex/alpha');

    unlink($path);
});

it('corex:modules:compile writes the compiled registry file for the discovered modules', function (): void {
    $this->app->instance(
        ModuleDiscovery::class,
        new ModuleDiscovery(fallbackModules: [StubModuleDefinition::class]),
    );

    $exitCode = Artisan::call('corex:modules:compile');

    expect($exitCode)->toBe(0);

    $cachePath = CompiledModuleRegistry::defaultCachePath();
    $data = require $cachePath;

    expect($data['modules'])->toHaveCount(1)
        ->and($data['modules'][0]['composerName'])->toBe('corex/stub-module')
        ->and($data['modules'][0]['tablePrefix'])->toBe('stb');

    unlink($cachePath);
});

it('corex:modules:compile persists the entity, permissions and menu slices, not just a counter (A10)', function (): void {
    $this->app->instance(
        ModuleDiscovery::class,
        new ModuleDiscovery(fallbackModules: [P15CompiledStubModule::class]),
    );

    expect(Artisan::call('corex:modules:compile'))->toBe(0);

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
    Artisan::call('corex:modules:compile');
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
    Artisan::call('corex:modules:compile');
    $cachePath = CompiledModuleRegistry::defaultCachePath();

    $this->app->register(new ModulesServiceProvider($this->app), force: true);

    expect(Relation::getMorphedModel('pft.thing'))->toBe(Widget::class);

    unlink($cachePath);
});

it('reads the persisted registry back through compiled()', function (): void {
    $path = moduleRegistryCachePath();
    $compiled = (new RegistryCompiler)->compile([(new P15CompiledStubModule)->manifest()]);
    CompiledModuleRegistry::writeAtomic($path, [], $compiled);

    $registry = newModuleRegistry($path, new ArrayModuleStateReader);
    $read = $registry->compiled();

    expect($read->entities)->toHaveKey('pft.thing')
        ->and($read->entities['pft.thing']->model)->toBe(Widget::class)
        ->and($read->permissions)->toHaveCount(1);

    unlink($path);
});

it('writeAtomic throws and leaves the destination intact when the atomic move fails', function (): void {
    // Destination is an existing directory — rename(tmpFile, dir) fails with
    // EISDIR, so the failed-rename branch is the one exercised.
    $destination = sys_get_temp_dir().'/corex_modules_reg_'.bin2hex(random_bytes(4));
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
    $registry = newModuleRegistry(moduleRegistryCachePath(), new ArrayModuleStateReader, failIfMissing: true);

    expect(fn () => $registry->all())->toThrow(RuntimeException::class, 'corex:modules:compile')
        ->and(fn () => $registry->compiled())->toThrow(RuntimeException::class, 'corex:modules:compile');
});

it('returns an empty registry outside production when the compiled file is absent', function (): void {
    $registry = newModuleRegistry(moduleRegistryCachePath(), new ArrayModuleStateReader, failIfMissing: false);

    expect($registry->all())->toBe([])
        ->and($registry->compiled()->entities)->toBe([]);
});

it('reads active()/isActive() on a non-taggable file store without BadMethodCallException (A1/A2)', function (): void {
    // The Blocker: file/database stores are not taggable, so any tags() call
    // in the hot path throws. Pin the cache to the file store — the very store
    // the boxed profile (B-10 §6.1) uses and testbench's array default hid.
    $path = moduleRegistryCachePath();
    CompiledModuleRegistry::writeAtomic($path, [new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], [])]);

    $reader = new ArrayModuleStateReader([
        'acc-1' => ['corex/alpha' => ['state' => 'enabled', 'edition' => 'standard']],
    ]);
    $fileCache = new RepositoryModuleRegistryCache(
        Cache::store('file'),
        ModulesOptions::fromArray(['cache' => ['ttl' => 3600, 'prefix' => 'corex.modules-a1']]),
    );
    $registry = newModuleRegistry($path, $reader, cache: $fileCache);
    $ctx = moduleRegistryCtx('acc-1');

    expect($registry->isActive('corex/alpha', $ctx))->toBeTrue()
        ->and(array_map(static fn (CompiledModule $m): string => $m->composerName, $registry->active($ctx)))->toBe(['corex/alpha']);

    Cache::store('file')->flush();
    unlink($path);
});

it('reports isActive()=false for a module enabled in the reader but absent from the compiled registry (A45/A46)', function (): void {
    // A ghost row: mod_modules still lists a module whose code was removed
    // (composer remove / rename). isActive() must intersect with the compiled
    // registry, or the EntityRegistry predicate would pass a module get()
    // cannot resolve.
    $path = moduleRegistryCachePath();
    CompiledModuleRegistry::writeAtomic($path, [new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], [])]);

    $reader = new ArrayModuleStateReader([
        'acc-1' => [
            'corex/alpha' => ['state' => 'enabled', 'edition' => 'standard'],
            'corex/ghost' => ['state' => 'enabled', 'edition' => 'standard'],
        ],
    ]);
    $registry = newModuleRegistry($path, $reader);
    $ctx = moduleRegistryCtx('acc-1');

    expect($registry->isActive('corex/alpha', $ctx))->toBeTrue()
        ->and($registry->isActive('corex/ghost', $ctx))->toBeFalse()
        ->and(array_map(static fn (CompiledModule $m): string => $m->composerName, $registry->active($ctx)))->toBe(['corex/alpha']);

    unlink($path);
});

it('pins compiled() for one job and reloads only after flush', function (): void {
    $path = moduleRegistryCachePath();
    $first = (new RegistryCompiler)->compile([
        ModuleManifest::make('corex/alpha')->title('Alpha')->tablePrefix('alp'),
    ], []);
    CompiledModuleRegistry::writeAtomic($path, [new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], [])], $first);

    $registry = newModuleRegistry($path, new ArrayModuleStateReader);
    $pinned = $registry->compiled();
    $second = (new RegistryCompiler)->compile([
        ModuleManifest::make('corex/beta')->title('Beta')->tablePrefix('bet'),
    ], []);
    CompiledModuleRegistry::writeAtomic($path, [new CompiledModule('corex/beta', 'Beta', 'bet', [], [], [])], $second);

    expect($registry->compiled())->not->toBe($pinned)
        ->and($registry->compiled()->toArray())->toBe($pinned->toArray())
        ->and($registry->compiled()->fingerprint)->toBe($first->fingerprint);

    $registry->flush();

    expect($registry->compiled()->fingerprint)->toBe($second->fingerprint)
        ->and($registry->compiled()->fingerprint)->not->toBe($first->fingerprint);

    unlink($path);
});

it('rejects a corrupt compiled artifact and keeps activation off the fingerprint', function (): void {
    $path = moduleRegistryCachePath();
    $compiled = (new RegistryCompiler)->compile([
        ModuleManifest::make('corex/alpha')->title('Alpha')->tablePrefix('alp'),
    ], []);
    CompiledModuleRegistry::writeAtomic($path, [new CompiledModule('corex/alpha', 'Alpha', 'alp', [], [], [])], $compiled);

    $reader = new ArrayModuleStateReader([
        'acc-1' => ['corex/alpha' => ['state' => 'enabled', 'edition' => 'standard']],
    ]);
    $epoch = 0;
    $registry = newModuleRegistry($path, $reader, versionResolver: static function () use (&$epoch): int {
        return $epoch;
    });
    $fingerprint = $registry->compiled()->fingerprint;

    expect($registry->isActive('corex/alpha', moduleRegistryCtx('acc-1')))->toBeTrue();
    $epoch = 4;
    expect($registry->compiled()->fingerprint)->toBe($fingerprint);

    file_put_contents($path, "<?php\nreturn ['modules' => [], 'registry' => ['entities' => []]];\n");
    $registry->flush();

    expect(fn () => $registry->compiled())->toThrow(CompilationException::class, 'missing schema');

    unlink($path);
});

it('builds the registry cache from configured Options — store, ttl and prefix are read, not hardcoded (A45/A46)', function (): void {
    // Point the module cache at a named array store and a custom prefix, then
    // assert the resolved cache writes the epoch-stamped key THERE — proving
    // config('corex.modules.cache.*') drives the binding (no hardcoded 3600).
    config()->set('corex.modules.cache.store', 'array');
    config()->set('corex.modules.cache.prefix', 'cfg-driven');
    config()->set('corex.modules.cache.ttl', 3600);

    // Options is a provider-time snapshot. Replace it after changing test
    // config, then rebuild the already-resolved cache singleton.
    $this->app->instance(ModulesOptions::class, ModulesOptions::fromArray(config('corex.modules')));
    $this->app->forgetInstance(ModuleRegistryCache::class);
    $cache = $this->app->make(ModuleRegistryCache::class);
    $cache->rememberEnabled('acc-9', 7, static fn (): array => ['corex/alpha' => ['state' => 'enabled', 'edition' => 'standard']]);

    expect(Cache::store('array')->get('cfg-driven:account:acc-9:v7:enabled'))
        ->toBe(['corex/alpha' => ['state' => 'enabled', 'edition' => 'standard']]);
});
