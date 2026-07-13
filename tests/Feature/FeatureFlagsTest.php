<?php

declare(strict_types=1);

use CoreX\Contracts\FeatureFlagDefinition;
use CoreX\Contracts\SettingsScope;
use CoreX\Enums\SettingScope;
use CoreX\Events\DomainEvent;
use CoreX\Events\FeatureFlagToggled;
use CoreX\Events\SettingChanged;
use CoreX\Features\DatabaseFeatureFlags;
use CoreX\Models\FeatureFlag;
use CoreX\Models\FeatureOverride;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

const FEATURE_FLAGS_TEST_CONNECTION = 'pgsql';
const FEATURE_FLAGS_WORKSPACE_1 = '00000000-0000-0000-0000-000000000101';
const FEATURE_FLAGS_WORKSPACE_2 = '00000000-0000-0000-0000-000000000102';
const FEATURE_FLAGS_USER_1 = '00000000-0000-0000-0000-000000000001';

function featureFlagsPg(): Connection
{
    return DB::connection(FEATURE_FLAGS_TEST_CONNECTION);
}

function featureFlags(): DatabaseFeatureFlags
{
    return new DatabaseFeatureFlags(connection: FEATURE_FLAGS_TEST_CONNECTION);
}

beforeEach(function (): void {
    // D12: sys_feature_flags/overrides are PG-only. Reset to a clean public
    // schema so each test starts with no migration history (mirrors
    // SettingsTest's/AuditLogTest's pg-lane pattern).
    featureFlagsPg()->statement('DROP SCHEMA IF EXISTS public CASCADE');
    featureFlagsPg()->statement('CREATE SCHEMA public');
    DB::purge(FEATURE_FLAGS_TEST_CONNECTION);

    $this->artisan('migrate', [
        '--database' => FEATURE_FLAGS_TEST_CONNECTION,
        '--path' => realpath(__DIR__.'/../../packages/core/database/migrations/pg'),
        '--realpath' => true,
        '--force' => true,
    ])->run();
});

it('stamps a DomainEvent with a UUIDv7 eventId, occurredAt and a stable name()', function (): void {
    $event = new SettingChanged(
        namespace: 'crm',
        key: 'theme',
        scopeType: SettingScope::Account,
        scopeId: null,
        old: 'a',
        new: 'b',
        sensitive: false,
    );

    expect($event)->toBeInstanceOf(DomainEvent::class)
        ->and(Str::isUuid($event->eventId))->toBeTrue()
        ->and($event->eventId[14])->toBe('7') // UUIDv7 version nibble
        ->and($event->occurredAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($event::name())->toBe('core.setting.changed');
});

it('FeatureFlagToggled extends DomainEvent with its own stable name()', function (): void {
    $event = new FeatureFlagToggled(key: 'crm.lead_mode', scope: null, isEnabled: true);

    expect($event)->toBeInstanceOf(DomainEvent::class)
        ->and($event::name())->toBe('core.feature.toggled')
        ->and(Str::isUuid($event->eventId))->toBeTrue();
});

it('define() is idempotent and does not stomp an admin-toggled is_enabled', function (): void {
    $flags = featureFlags();

    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false, module: 'crm'));
    expect($flags->active('crm.lead_mode'))->toBeFalse();

    FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->update(['is_enabled' => true]);

    // Re-registering the same key (e.g. re-running module enable) must not
    // reset the admin's toggle back to the manifest default.
    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false, module: 'crm'));

    expect($flags->active('crm.lead_mode'))->toBeTrue()
        ->and(FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->count())->toBe(1);
});

it('AC-22: a narrower workspace override wins over the account-level default', function (): void {
    $flags = featureFlags();
    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false));

    expect($flags->active('crm.lead_mode'))->toBeFalse()
        ->and($flags->active('crm.lead_mode', SettingsScope::workspace(FEATURE_FLAGS_WORKSPACE_1)))->toBeFalse();

    $flagId = FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->value('id');

    FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->create([
        'flag_id' => $flagId,
        'scope_type' => 'workspace',
        'scope_id' => FEATURE_FLAGS_WORKSPACE_1,
        'is_enabled' => true,
        'payload' => ['variant' => 'beta'],
    ]);

    expect($flags->active('crm.lead_mode', SettingsScope::workspace(FEATURE_FLAGS_WORKSPACE_1)))->toBeTrue()
        ->and($flags->value('crm.lead_mode', SettingsScope::workspace(FEATURE_FLAGS_WORKSPACE_1)))->toBe(['variant' => 'beta'])
        // A different workspace and the bare account-level read stay unaffected.
        ->and($flags->active('crm.lead_mode', SettingsScope::workspace(FEATURE_FLAGS_WORKSPACE_2)))->toBeFalse()
        ->and($flags->active('crm.lead_mode'))->toBeFalse();
});

it('a user-scoped override is independent of a workspace-scoped one', function (): void {
    $flags = featureFlags();
    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false));
    $flagId = FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->value('id');

    FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->create([
        'flag_id' => $flagId,
        'scope_type' => 'user',
        'scope_id' => FEATURE_FLAGS_USER_1,
        'is_enabled' => true,
    ]);

    expect($flags->active('crm.lead_mode', SettingsScope::user(FEATURE_FLAGS_USER_1)))->toBeTrue()
        ->and($flags->active('crm.lead_mode', SettingsScope::workspace(FEATURE_FLAGS_WORKSPACE_1)))->toBeFalse();
});

it('cascades override delete when its flag is deleted (FK ON DELETE CASCADE)', function (): void {
    $flags = featureFlags();
    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false));
    $flagId = FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->value('id');

    FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->create([
        'flag_id' => $flagId,
        'scope_type' => 'workspace',
        'scope_id' => FEATURE_FLAGS_WORKSPACE_1,
        'is_enabled' => true,
    ]);

    expect(FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->where('flag_id', $flagId)->count())->toBe(1);

    FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('id', $flagId)->delete();

    expect(FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->where('flag_id', $flagId)->count())->toBe(0);
});

it('rejects an override scope_type outside workspace/user/department (CHECK constraint)', function (): void {
    $flags = featureFlags();
    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false));
    $flagId = FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->value('id');

    expect(fn () => FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->create([
        'flag_id' => $flagId,
        'scope_type' => 'account',
        'scope_id' => FEATURE_FLAGS_WORKSPACE_1,
        'is_enabled' => true,
    ]))->toThrow(QueryException::class);
});
