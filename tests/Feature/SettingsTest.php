<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Settings\Data\SettingsScope;
use CoreX\Settings\Events\SettingChanged;
use CoreX\Settings\Exceptions\SettingValueTooLargeException;
use CoreX\Settings\Exceptions\UnsupportedScopeException;
use CoreX\Settings\Internal\DatabaseSettingsRepository;
use CoreX\Tests\Stubs\StubSettingDefaultsProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Compiler\ModuleCompilation;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use Packman\Modules\Data\Settings\DefaultSettingDefinition;
use Packman\Settings\Contracts\SettingDefaultsProviderInterface as SettingDefaultsProvider;

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
    return app()->make(DatabaseSettingsRepository::class, ['defaults' => new StubSettingDefaultsProvider($defaults, $sensitive), 'connection' => SETTINGS_TEST_CONNECTION]);
}

beforeEach(function (): void {
    // D12: sys_settings is PG-only (jsonb/CHECK/UNIQUE NULLS NOT DISTINCT).
    // Reset to a clean public schema so each test starts with no migration
    // history (mirrors ModuleLifecycleTest's pg-lane pattern).
    settingsPg()->statement('DROP SCHEMA IF EXISTS public CASCADE');
    settingsPg()->statement('CREATE SCHEMA public');
    DB::purge(SETTINGS_TEST_CONNECTION);

    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], SETTINGS_TEST_CONNECTION);
});

it('cascades user over workspace over account over manifest default', function (): void {
    $repository = settingsRepositoryFor(defaults: ['crm.theme' => 'default-theme']);

    // Nothing stored anywhere yet: manifest default wins.
    expect(JsonDocuments::decode($repository->get('crm', 'theme', SettingsScope::user(SETTINGS_USER_1), JsonDocuments::capture('fallback'))))->toBe('default-theme');

    $repository->set('crm', 'theme', JsonDocuments::capture('account-theme'));
    expect(JsonDocuments::decode($repository->get('crm', 'theme', SettingsScope::user(SETTINGS_USER_1))))->toBe('account-theme');

    $repository->set('crm', 'theme', JsonDocuments::capture('workspace-theme'), SettingsScope::workspace(SETTINGS_WORKSPACE_1));
    expect(JsonDocuments::decode($repository->get('crm', 'theme', SettingsScope::workspace(SETTINGS_WORKSPACE_1))))->toBe('workspace-theme');

    $repository->set('crm', 'theme', JsonDocuments::capture('user-theme'), SettingsScope::user(SETTINGS_USER_1));
    expect(JsonDocuments::decode($repository->get('crm', 'theme', SettingsScope::user(SETTINGS_USER_1))))->toBe('user-theme');

    // Account level is untouched by the narrower writes above.
    expect(JsonDocuments::decode($repository->get('crm', 'theme')))->toBe('account-theme');

    // No default when nothing is stored and no manifest default exists.
    expect(JsonDocuments::decode($repository->get('crm', 'missing', null, JsonDocuments::capture('fallback'))))->toBe('fallback');
});

it('lets a narrower scope override a broader one without leaking across ids', function (): void {
    $repository = settingsRepositoryFor();

    $repository->set('crm', 'locale', JsonDocuments::capture('ru'), SettingsScope::user(SETTINGS_USER_1));

    expect(JsonDocuments::decode($repository->get('crm', 'locale', SettingsScope::user(SETTINGS_USER_1), JsonDocuments::capture('en'))))->toBe('ru')
        ->and(JsonDocuments::decode($repository->get('crm', 'locale', SettingsScope::user(SETTINGS_USER_2), JsonDocuments::capture('en'))))->toBe('en')
        ->and(JsonDocuments::decode($repository->get('crm', 'locale', null, JsonDocuments::capture('en'))))->toBe('en');
});

it('encrypts a sensitive value at rest and masks it in SettingChanged', function (): void {
    // Faking ONLY SettingChanged (not a blanket Event::fake()): a bare fake
    // also intercepts Eloquent's internal model events, which the persistence
    // path and audit integration still need.
    Event::fake([SettingChanged::class]);
    $repository = settingsRepositoryFor(sensitive: ['crm.api_key']);

    $repository->set('crm', 'api_key', JsonDocuments::capture('super-secret'));

    $raw = settingsPg()->table('sys_settings')
        ->where('namespace', 'crm')->where('key', 'api_key')
        ->value('value');

    expect($raw)->not->toContain('super-secret')
        ->and(JsonDocuments::decode($repository->get('crm', 'api_key')))->toBe('super-secret');

    Event::assertDispatched(SettingChanged::class, fn (SettingChanged $event): bool => $event->namespace === 'crm'
        && $event->key === 'api_key'
        && $event->sensitive === true
        && JsonDocuments::decode($event->old) === '••••••'
        && JsonDocuments::decode($event->new) === '••••••');
});

it('updates in place through the repository rather than duplicating a row', function (): void {
    $repository = settingsRepositoryFor();

    $repository->set('crm', 'dup', JsonDocuments::capture('first'));
    $repository->set('crm', 'dup', JsonDocuments::capture('second'));

    expect(JsonDocuments::decode($repository->get('crm', 'dup')))->toBe('second')
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
    // The dispatcher is injected: fake it before the repository is built.
    Event::fake([SettingChanged::class]);
    $repository = settingsRepositoryFor();
    $repository->set('crm', 'theme', JsonDocuments::capture('stable'));
    $repository->set('crm', 'theme', JsonDocuments::capture('stable'));

    Event::assertDispatchedTimes(SettingChanged::class, 1);
});

it('fires SettingChanged once per actual value change', function (): void {
    Event::fake([SettingChanged::class]);
    $repository = settingsRepositoryFor();
    $repository->set('crm', 'theme', JsonDocuments::capture('first'));
    $repository->set('crm', 'theme', JsonDocuments::capture('second'));

    Event::assertDispatchedTimes(SettingChanged::class, 2);
});

it('all() merges the cascade with the narrower scope winning per key', function (): void {
    $repository = settingsRepositoryFor();

    $repository->set('crm', 'theme', JsonDocuments::capture('account-theme'));
    $repository->set('crm', 'locale', JsonDocuments::capture('en'));
    $repository->set('crm', 'theme', JsonDocuments::capture('user-theme'), SettingsScope::user(SETTINGS_USER_1));

    expect(JsonDocuments::decodeTree($repository->all('crm', SettingsScope::user(SETTINGS_USER_1))))
        ->toBe(['theme' => 'user-theme', 'locale' => 'en'])
        ->and(JsonDocuments::decodeTree($repository->all('crm')))->toBe(['theme' => 'account-theme', 'locale' => 'en']);
});

it('forget() removes exactly its own scope level, leaving other levels intact', function (): void {
    $repository = settingsRepositoryFor();

    $repository->set('crm', 'theme', JsonDocuments::capture('account-theme'));
    $repository->set('crm', 'theme', JsonDocuments::capture('user-theme'), SettingsScope::user(SETTINGS_USER_1));

    $repository->forget('crm', 'theme', SettingsScope::user(SETTINGS_USER_1));

    expect(JsonDocuments::decode($repository->get('crm', 'theme', SettingsScope::user(SETTINGS_USER_1))))->toBe('account-theme')
        ->and(JsonDocuments::decode($repository->get('crm', 'theme')))->toBe('account-theme');
});

it('rejects a department scope on every method (sys_settings has no department level, D38)', function (): void {
    $repository = settingsRepositoryFor();
    $department = SettingsScope::department('00000000-0000-0000-0000-000000000301');

    expect(fn () => JsonDocuments::decode($repository->get('crm', 'theme', $department)))->toThrow(UnsupportedScopeException::class)
        ->and(fn () => $repository->set('crm', 'theme', JsonDocuments::capture('x'), $department))->toThrow(UnsupportedScopeException::class)
        ->and(fn () => $repository->forget('crm', 'theme', $department))->toThrow(UnsupportedScopeException::class)
        ->and(fn () => JsonDocuments::decodeTree($repository->all('crm', $department)))->toThrow(UnsupportedScopeException::class);
});

it('rejects a value over the 64KB quota', function (): void {
    $repository = settingsRepositoryFor();

    $tooLarge = str_repeat('x', 70_000);

    expect(fn () => $repository->set('crm', 'huge', JsonDocuments::capture($tooLarge)))
        ->toThrow(SettingValueTooLargeException::class);
});

it('resolves manifest setting defaults and sensitivity through the CoreX container', function (): void {
    $manifest = ModuleManifest::make('corex/stub-settings')
        ->title('Stub Settings')
        ->tablePrefix('sst')
        ->settings([
            DefaultSettingDefinition::make('theme', 'manifest-default')->sensitive(),
        ]);

    $compiled = resolve(ModuleCompilation::class)->compile([$manifest]);
    $registry = Mockery::mock(ModuleRegistry::class);
    $registry->shouldReceive('compiled')->andReturn($compiled);
    $this->app->instance(ModuleRegistry::class, $registry);

    /** @var SettingDefaultsProvider $defaults */
    $defaults = $this->app->make(SettingDefaultsProvider::class);

    expect($defaults->defaultFor('sst', 'theme'))->toBe('manifest-default')
        ->and($defaults->isSensitive('sst', 'theme'))->toBeTrue()
        ->and($defaults->defaultFor('sst', 'missing'))->toBeNull();

    $repository = app()->make(DatabaseSettingsRepository::class, ['defaults' => $defaults, 'connection' => SETTINGS_TEST_CONNECTION]);

    expect(JsonDocuments::decode($repository->get('sst', 'theme')))->toBe('manifest-default')
        ->and(settingsPg()->table('sys_settings')->where('namespace', 'sst')->where('key', 'theme')->exists())->toBeFalse();
});

it('uses the host-configured defaults provider ahead of the module defaults', function (): void {
    config(['corex.bindings.setting_defaults' => StubSettingDefaultsProvider::class]);
    $this->app->forgetInstance(CoreOptions::class);
    $defaults = new StubSettingDefaultsProvider(['sst.theme' => 'host'], ['sst.theme']);
    $this->app->instance(StubSettingDefaultsProvider::class, $defaults);

    expect($this->app->make(SettingDefaultsProvider::class))->toBe($defaults);
});
