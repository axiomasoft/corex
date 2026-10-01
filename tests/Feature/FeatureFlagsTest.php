<?php

declare(strict_types=1);

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Features\Data\FeatureFlagDefinition;
use CoreX\Features\Events\FeatureFlagToggled;
use CoreX\Features\Internal\DatabaseFeatureFlags;
use CoreX\Features\Internal\Models\FeatureFlag;
use CoreX\Features\Internal\Models\FeatureOverride;
use CoreX\Messaging\Events\DomainEvent;
use CoreX\Settings\Data\SettingsScope;
use CoreX\Settings\Enums\SettingScope;
use CoreX\Settings\Events\SettingChanged;
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
        old: JsonDocuments::capture('a'),
        new: JsonDocuments::capture('b'),
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
        ->and(JsonDocuments::decode($flags->value('crm.lead_mode', SettingsScope::workspace(FEATURE_FLAGS_WORKSPACE_1))))->toBe(['variant' => 'beta'])
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

it('AC-22 (a): a user-override wins for a user-scoped read, and the workspace-override is independently reachable', function (): void {
    $flags = featureFlags();
    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false));
    $flagId = FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->value('id');

    // Both overrides deliberately disagree with the account-level default
    // (false) AND with each other, so a broken cascade (e.g. workspace
    // silently falling back to the account default) is observable.
    FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->create([
        'flag_id' => $flagId,
        'scope_type' => 'workspace',
        'scope_id' => FEATURE_FLAGS_WORKSPACE_1,
        'is_enabled' => true,
    ]);
    FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->create([
        'flag_id' => $flagId,
        'scope_type' => 'user',
        'scope_id' => FEATURE_FLAGS_USER_1,
        'is_enabled' => false,
    ]);

    expect($flags->active('crm.lead_mode', SettingsScope::user(FEATURE_FLAGS_USER_1)))->toBeFalse()
        ->and($flags->active('crm.lead_mode', SettingsScope::workspace(FEATURE_FLAGS_WORKSPACE_1)))->toBeTrue();
});

it('AC-22 (b): a department-override is independently reachable, and the workspace-override still resolves alongside it', function (): void {
    $flags = featureFlags();
    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false));
    $flagId = FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->value('id');
    $departmentId = '00000000-0000-0000-0000-000000000201';

    // Both overrides deliberately disagree with the account-level default
    // (false), same rationale as AC-22 (a).
    FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->create([
        'flag_id' => $flagId,
        'scope_type' => 'workspace',
        'scope_id' => FEATURE_FLAGS_WORKSPACE_1,
        'is_enabled' => true,
    ]);
    FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->create([
        'flag_id' => $flagId,
        'scope_type' => 'department',
        'scope_id' => $departmentId,
        'is_enabled' => true,
    ]);

    expect($flags->active('crm.lead_mode', SettingsScope::department($departmentId)))->toBeTrue()
        ->and($flags->active('crm.lead_mode', SettingsScope::workspace(FEATURE_FLAGS_WORKSPACE_1)))->toBeTrue();
});

it('AC-22 (c): falls back to the account-level default when no narrow override matches', function (): void {
    $flags = featureFlags();
    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: true));

    expect($flags->active('crm.lead_mode', SettingsScope::user(FEATURE_FLAGS_USER_1)))->toBeTrue()
        ->and($flags->active('crm.lead_mode', SettingsScope::department('00000000-0000-0000-0000-000000000202')))->toBeTrue();
});

it('AC-22 (d): value() falls back to the flag payload when the override payload is NULL', function (): void {
    $flags = featureFlags();
    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false, payload: JsonDocuments::nullable(['variant' => 'flag-level'])));
    $flagId = FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->value('id');

    // This override only toggles is_enabled — payload NULL must not blank
    // the flag's own payload out (A87).
    FeatureOverride::on(FEATURE_FLAGS_TEST_CONNECTION)->create([
        'flag_id' => $flagId,
        'scope_type' => 'workspace',
        'scope_id' => FEATURE_FLAGS_WORKSPACE_1,
        'is_enabled' => true,
        'payload' => null,
    ]);

    expect(JsonDocuments::decode($flags->value('crm.lead_mode', SettingsScope::workspace(FEATURE_FLAGS_WORKSPACE_1))))
        ->toBe(['variant' => 'flag-level']);
});

it('define() is atomic under a racing second define() for the same key (UNIQUE(key), no read-then-create)', function (): void {
    $flags = featureFlags();
    $second = new DatabaseFeatureFlags(connection: FEATURE_FLAGS_TEST_CONNECTION);

    $flags->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false, module: 'crm'));
    FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->update(['is_enabled' => true]);

    // A second definer (e.g. a concurrent module-enable on another
    // connection instance) racing the same key must not stomp the
    // admin-toggled is_enabled, and must not throw out to the caller.
    $second->define(new FeatureFlagDefinition(key: 'crm.lead_mode', default: false, module: 'crm'));

    expect(FeatureFlag::on(FEATURE_FLAGS_TEST_CONNECTION)->where('key', 'crm.lead_mode')->count())->toBe(1)
        ->and($flags->active('crm.lead_mode'))->toBeTrue();
});
