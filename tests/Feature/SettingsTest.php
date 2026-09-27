<?php

declare(strict_types=1);

use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Contracts\SettingsRepository;
use CoreX\Contracts\SettingsScope;
use CoreX\Events\SettingChanged;
use CoreX\Exceptions\SettingValueTooLargeException;
use CoreX\Exceptions\UnsupportedScopeException;
use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\DefaultSettingDefinition;
use CoreX\Modules\Lifecycle\DatabaseModuleStateReader;
use CoreX\Modules\ModuleManifest;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Modules\Registry\CompiledModuleRegistry;
use CoreX\Modules\Registry\RepositoryModuleRegistryCache;
use CoreX\Modules\Support\ModulesOptions;
use CoreX\Settings\DatabaseSettingsRepository;
use CoreX\Tenancy\TenantContext;
use CoreX\Tests\Stubs\StubSettingDefaultsProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses()->group('pg');

const SETTINGS_TEST_CONNECTION = 'pgsql';
const SETTINGS_USER_1 = '00000000-0000-0000-0000-000000000001';
const SETTINGS_USER_2 = '00000000-0000-0000-0000-000000000002';
const SETTINGS_WORKSPACE_1 = '00000000-0000-0000-0000-000000000101';

function settingsPg(): Connection
{
    return DB::connection(SETTINGS_TEST_CONNECTION);
}

/**
 * @param  array<string, mixed>  $defaults
 * @param  list<string>  $sensitive
 */
function settingsRepositoryFor(array $defaults = [], array $sensitive = []): SettingsRepository
{
    return new DatabaseSettingsRepository(
        defaults: new StubSettingDefaultsProvider($defaults, $sensitive),
        connection: SETTINGS_TEST_CONNECTION,
    );
}

beforeEach(function (): void {
    // D12: sys_settings is PG-only (jsonb/CHECK/UNIQUE NULLS NOT DISTINCT).
    // Reset to a clean public schema so each test starts with no migration
    // history (mirrors ModuleLifecycleTest's pg-lane pattern).
    settingsPg()->statement('DROP SCHEMA IF EXISTS public CASCADE');
    settingsPg()->statement('CREATE SCHEMA public');
    DB::purge(SETTINGS_TEST_CONNECTION);

    $this->artisan('migrate', [
        '--database' => SETTINGS_TEST_CONNECTION,
        '--path' => realpath(__DIR__.'/../../packages/core/database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->run();
});

it('cascades user over workspace over account over manifest default', function (): void {
    $repository = settingsRepositoryFor(defaults: ['crm.theme' => 'default-theme']);

    // Nothing stored anywhere yet: manifest default wins.
    expect($repository->get('crm', 'theme', SettingsScope::user(SETTINGS_USER_1), 'fallback'))->toBe('default-theme');

    $repository->set('crm', 'theme', 'account-theme');
    expect($repository->get('crm', 'theme', SettingsScope::user(SETTINGS_USER_1)))->toBe('account-theme');

    $repository->set('crm', 'theme', 'workspace-theme', SettingsScope::workspace(SETTINGS_WORKSPACE_1));
    expect($repository->get('crm', 'theme', SettingsScope::workspace(SETTINGS_WORKSPACE_1)))->toBe('workspace-theme');

    $repository->set('crm', 'theme', 'user-theme', SettingsScope::user(SETTINGS_USER_1));
    expect($repository->get('crm', 'theme', SettingsScope::user(SETTINGS_USER_1)))->toBe('user-theme');

    // Account level is untouched by the narrower writes above.
    expect($repository->get('crm', 'theme'))->toBe('account-theme');

    // No default when nothing is stored and no manifest default exists.
    expect($repository->get('crm', 'missing', null, 'fallback'))->toBe('fallback');
});

it('lets a narrower scope override a broader one without leaking across ids', function (): void {
    $repository = settingsRepositoryFor();

    $repository->set('crm', 'locale', 'ru', SettingsScope::user(SETTINGS_USER_1));

    expect($repository->get('crm', 'locale', SettingsScope::user(SETTINGS_USER_1), 'en'))->toBe('ru')
        ->and($repository->get('crm', 'locale', SettingsScope::user(SETTINGS_USER_2), 'en'))->toBe('en')
        ->and($repository->get('crm', 'locale', null, 'en'))->toBe('en');
});

it('encrypts a sensitive value at rest and masks it in SettingChanged', function (): void {
    // Faking ONLY SettingChanged (not a blanket Event::fake()): a bare fake
    // also intercepts Eloquent's internal model events, which would starve
    // HasConfiguredId's creating() hook and leave `id` unset.
    Event::fake([SettingChanged::class]);
    $repository = settingsRepositoryFor(sensitive: ['crm.api_key']);

    $repository->set('crm', 'api_key', 'super-secret');

    $raw = settingsPg()->table('sys_settings')
        ->where('namespace', 'crm')->where('key', 'api_key')
        ->value('value');

    expect($raw)->not->toContain('super-secret')
        ->and($repository->get('crm', 'api_key'))->toBe('super-secret');

    Event::assertDispatched(SettingChanged::class, function (SettingChanged $event): bool {
        return $event->namespace === 'crm'
            && $event->key === 'api_key'
            && $event->sensitive === true
            && $event->old === '••••••'
            && $event->new === '••••••';
    });
});

it('updates in place through the repository rather than duplicating a row', function (): void {
    $repository = settingsRepositoryFor();

    $repository->set('crm', 'dup', 'first');
    $repository->set('crm', 'dup', 'second');

    expect($repository->get('crm', 'dup'))->toBe('second')
        ->and(settingsPg()->table('sys_settings')->where('namespace', 'crm')->where('key', 'dup')->count())->toBe(1);
});

it('rejects a duplicate account-level row at the DB constraint (UNIQUE NULLS NOT DISTINCT), bypassing the repository', function (): void {
    // A57/A63: the repository always goes through updateOrCreate(), which
    // never emits a second INSERT for an existing row — that path can never
    // exercise the constraint. Hit the table directly with a raw duplicate
    // insert so the CHECK is the thing actually under test.
    settingsPg()->table('sys_settings')->insert([
        'id' => (string) Str::uuid7(),
        'namespace' => 'crm',
        'key' => 'dup-raw',
        'scope_type' => 'account',
        'scope_id' => null,
        'value' => json_encode('first', JSON_THROW_ON_ERROR),
        'is_sensitive' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => settingsPg()->table('sys_settings')->insert([
        'id' => (string) Str::uuid7(),
        'namespace' => 'crm',
        'key' => 'dup-raw',
        'scope_type' => 'account',
        'scope_id' => null,
        'value' => json_encode('second', JSON_THROW_ON_ERROR),
        'is_sensitive' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('does not fire SettingChanged when set() re-writes the same value', function (): void {
    $repository = settingsRepositoryFor();
    $repository->set('crm', 'theme', 'stable');

    Event::fake([SettingChanged::class]);
    $repository->set('crm', 'theme', 'stable');

    Event::assertNotDispatched(SettingChanged::class);
});

it('fires SettingChanged once per actual value change', function (): void {
    $repository = settingsRepositoryFor();

    Event::fake([SettingChanged::class]);
    $repository->set('crm', 'theme', 'first');
    $repository->set('crm', 'theme', 'second');

    Event::assertDispatchedTimes(SettingChanged::class, 2);
});

it('all() merges the cascade with the narrower scope winning per key', function (): void {
    $repository = settingsRepositoryFor();

    $repository->set('crm', 'theme', 'account-theme');
    $repository->set('crm', 'locale', 'en');
    $repository->set('crm', 'theme', 'user-theme', SettingsScope::user(SETTINGS_USER_1));

    expect($repository->all('crm', SettingsScope::user(SETTINGS_USER_1)))
        ->toBe(['theme' => 'user-theme', 'locale' => 'en'])
        ->and($repository->all('crm'))->toBe(['theme' => 'account-theme', 'locale' => 'en']);
});

it('forget() removes exactly its own scope level, leaving other levels intact', function (): void {
    $repository = settingsRepositoryFor();

    $repository->set('crm', 'theme', 'account-theme');
    $repository->set('crm', 'theme', 'user-theme', SettingsScope::user(SETTINGS_USER_1));

    $repository->forget('crm', 'theme', SettingsScope::user(SETTINGS_USER_1));

    expect($repository->get('crm', 'theme', SettingsScope::user(SETTINGS_USER_1)))->toBe('account-theme')
        ->and($repository->get('crm', 'theme'))->toBe('account-theme');
});

it('rejects a department scope on every method (sys_settings has no department level, D38)', function (): void {
    $repository = settingsRepositoryFor();
    $department = SettingsScope::department('00000000-0000-0000-0000-000000000301');

    expect(fn () => $repository->get('crm', 'theme', $department))->toThrow(UnsupportedScopeException::class)
        ->and(fn () => $repository->set('crm', 'theme', 'x', $department))->toThrow(UnsupportedScopeException::class)
        ->and(fn () => $repository->forget('crm', 'theme', $department))->toThrow(UnsupportedScopeException::class)
        ->and(fn () => $repository->all('crm', $department))->toThrow(UnsupportedScopeException::class);
});

it('rejects a value over the 64KB quota', function (): void {
    $repository = settingsRepositoryFor();

    $tooLarge = str_repeat('x', 70_000);

    expect(fn () => $repository->set('crm', 'huge', $tooLarge))
        ->toThrow(SettingValueTooLargeException::class);
});

it('resolves a manifest-declared DefaultSettingDefinition through the container-bound SettingDefaultsProvider once corex/modules is registered (P1.25, A68, test в)', function (): void {
    // Registering corex/modules must rebind the boxed Null default
    // (CoreServiceProvider::register()) to CompiledSettingDefaultsProvider —
    // reading the manifest default without any row in sys_settings is
    // exactly the wiring gap A68/Pending Work-P1 left open.
    $this->app->register(ModulesServiceProvider::class);

    $manifest = ModuleManifest::make('corex/stub-settings')
        ->title('Stub Settings')
        ->tablePrefix('sst')
        ->settings([
            DefaultSettingDefinition::make('theme', 'manifest-default')->sensitive(),
        ]);

    $compiled = (new RegistryCompiler)->compile([$manifest]);
    $path = sys_get_temp_dir().'/corex_modules_settings_test_'.bin2hex(random_bytes(4)).'.php';
    CompiledModuleRegistry::writeAtomic($path, [], $compiled);

    try {
        $this->app->instance(ModuleRegistry::class, new CompiledModuleRegistry(
            cachePath: $path,
            stateReader: new DatabaseModuleStateReader(connection: SETTINGS_TEST_CONNECTION),
            cache: new RepositoryModuleRegistryCache(
                Cache::store(),
                ModulesOptions::fromArray(['cache' => ['ttl' => 3600, 'prefix' => 'corex-modules']]),
            ),
            versionResolver: static fn (TenantContext $ctx): int => 0,
        ));

        // Pulled straight from the container (not hand-built) — the seam the
        // mutation-check pins: reverting ModulesServiceProvider's rebind to
        // NullSettingDefaultsProvider makes this resolve to Null instead.
        /** @var SettingDefaultsProvider $defaults */
        $defaults = $this->app->make(SettingDefaultsProvider::class);

        expect($defaults->defaultFor('sst', 'theme'))->toBe('manifest-default')
            ->and($defaults->isSensitive('sst', 'theme'))->toBeTrue()
            ->and($defaults->defaultFor('sst', 'missing'))->toBeNull();

        // sys_settings carries no row for it — the manifest default alone
        // reaches SettingsRepository::get().
        $repository = new DatabaseSettingsRepository(defaults: $defaults, connection: SETTINGS_TEST_CONNECTION);

        expect($repository->get('sst', 'theme'))->toBe('manifest-default')
            ->and(settingsPg()->table('sys_settings')->where('namespace', 'sst')->where('key', 'theme')->exists())->toBeFalse();
    } finally {
        unlink($path);
    }
});
