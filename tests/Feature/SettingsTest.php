<?php

declare(strict_types=1);

use CoreX\Contracts\SettingsRepository;
use CoreX\Contracts\SettingsScope;
use CoreX\Events\SettingChanged;
use CoreX\Exceptions\SettingValueTooLargeException;
use CoreX\Settings\DatabaseSettingsRepository;
use CoreX\Tests\Stubs\StubSettingDefaultsProvider;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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

it('does not allow a duplicate account-level setting (UNIQUE NULLS NOT DISTINCT)', function (): void {
    $repository = settingsRepositoryFor();

    $repository->set('crm', 'dup', 'first');
    $repository->set('crm', 'dup', 'second');

    expect($repository->get('crm', 'dup'))->toBe('second')
        ->and(settingsPg()->table('sys_settings')->where('namespace', 'crm')->where('key', 'dup')->count())->toBe(1);
});

it('rejects a value over the 64KB quota', function (): void {
    $repository = settingsRepositoryFor();

    $tooLarge = str_repeat('x', 70_000);

    expect(fn () => $repository->set('crm', 'huge', $tooLarge))
        ->toThrow(SettingValueTooLargeException::class);
});
