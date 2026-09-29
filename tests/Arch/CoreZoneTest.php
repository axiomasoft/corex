<?php

declare(strict_types=1);

use CoreX\Tests\Support\SourceScanner;

// P6.2 — ten-zone arch gate (D32/D101). Matrix is copied from the design, not read at runtime.
// PubSub/ is Messaging by path. ALLOWED['Foundation'] stays empty.

/**
 * @var array<string, list<string>>
 */
const CORE_ZONE_ALLOWED = [
    'Foundation' => [],
    'Modules' => ['Foundation', 'TenancyContracts', 'Audit', 'Settings', 'SavedViews', 'Storage', 'Messaging', 'Features', 'Departments', 'Money'],
    'TenancyContracts' => ['Foundation'],
    'Audit' => ['Foundation', 'TenancyContracts'],
    'Settings' => ['Foundation', 'TenancyContracts'],
    'SavedViews' => ['Foundation', 'TenancyContracts'],
    'Storage' => ['Foundation', 'TenancyContracts'],
    'Messaging' => ['Foundation', 'TenancyContracts'],
    'Features' => ['Foundation', 'TenancyContracts'],
    'Departments' => ['Foundation', 'TenancyContracts'],
    'Money' => ['Foundation', 'TenancyContracts'],
];

/** @var list<string> */
const CORE_ZONE_COMPOSITION = [
    'packages/core/src/Config/Internal/KernelConfigSection.php',
    'packages/core/src/CoreServiceProvider.php',
    'packages/core/src/Config/Config.php',
    'packages/core/src/Config/CoreOptions.php',
    'packages/core/src/Config/Internal/Commands/ValidateConfigCommand.php',
    'packages/core/src/Modules/Internal/ServiceProviderModuleRegistrar.php',
    'packages/core/src/Modules/Contracts/PackageRegistrar.php',
    'packages/core/src/Modules/RegistersNothingOnBoot.php',
];

const CORE_ZONE_E_BASEMODEL = 'packages/core/src/Database/BaseModel.php';

/**
 * Closed public edges (D116). Not an ALLOWED widening: any other symbol stays R-Z1.
 *
 * @var array<string, list<string>>
 */
const CORE_ZONE_EXACT_IMPORTS = [
    'packages/core/src/Context/ImpersonationGuard.php' => ['CoreX\\Audit\\Internal\\AuditLog'],
    'packages/core/src/Data/DataScope.php' => [
        'CoreX\\Context\\Data\\TenantContext',
    ],
    'packages/core/src/Messaging/Events/DomainEvent.php' => [
        'CoreX\\Context\\Data\\TenantContext',
        'CoreX\\Context\\Contracts\\TenantContextResolver',
    ],
    'packages/core/src/Features/Events/FeatureFlagToggled.php' => [
        'CoreX\\Settings\\Contracts\\SettingsScope',

        'CoreX\\Messaging\\Events\\DomainEvent',
    ],
    'packages/core/src/Features/DatabaseFeatureFlags.php' => [
        'CoreX\\Settings\\Contracts\\SettingsScope',
        'CoreX\\Settings\\Enums\\SettingScope',
    ],
    'packages/core/src/Context/GuardedTenantContextRestorer.php' => [
        'CoreX\\Context\\Data\\TenantContext',
    ],
    'packages/core/src/Context/NullTenancyManager.php' => [
        'CoreX\\Context\\Data\\AccountRef',
        'CoreX\\Context\\Data\\TenantContext',
        'CoreX\\Context\\Data\\WorkspaceRef',
    ],
    'packages/core/src/Context/SingleAccountResolver.php' => [
        'CoreX\\Context\\Data\\AccountRef',
        'CoreX\\Context\\Data\\TenantContext',
    ],
    'packages/core/src/Departments/Events/DepartmentMoved.php' => [
        'CoreX\\Messaging\\Events\\DomainEvent',
    ],
    'packages/core/src/Features/Contracts/FeatureFlags.php' => [
        'CoreX\\Settings\\Contracts\\SettingsScope',
    ],
    'packages/core/src/Settings/Events/SettingChanged.php' => [
        'CoreX\\Messaging\\Events\\DomainEvent',
    ],
    'packages/core/src/Messaging/Contracts/TraceContextBridge.php' => [
        'CoreX\\Observability\\Internal\\OperationContext',
    ],
    'packages/core/src/Messaging/Internal/NullTraceContextBridge.php' => [
        'CoreX\\Observability\\Internal\\OperationContext',
    ],
    'packages/core/src/Messaging/Internal/OpenTelemetryTraceContextBridge.php' => [
        'CoreX\\Observability\\Internal\\OpenTelemetryBridge',
        'CoreX\\Observability\\Internal\\OperationContext',
    ],
    'packages/core/src/Modules/Internal/Audit/AuditCapabilityWiring.php' => [
        'CoreX\\Audit\\Internal\\AuditObserver',
    ],
];

/**
 * Exact Foundation support types (D101). Any other unclassified Support file is R-Z0.
 *
 * @var list<string>
 */
const CORE_ZONE_FOUNDATION_SUPPORT = [
    'Config/ViolationList.php',
    'Config/Exceptions/InvalidConfiguration.php',
    'Config/SecretRef.php',
];

/**
 * Prefixes under packages/core/src/. Longer paths are listed before shorter ones.
 *
 * @var array<string, string>
 */
const CORE_ZONE_PREFIX = [
    'Audit/' => 'Audit',
    'Context/' => 'TenancyContracts',
    'Data/' => 'Foundation',
    'Departments/' => 'Departments',
    'Features/' => 'Features',
    'Messaging/' => 'Messaging',
    'Modules/' => 'Modules',
    'Money/' => 'Money',
    'Settings/' => 'Settings',
    'Storage/' => 'Storage',
    'Time/' => 'Foundation',
];

/**
 * Mixed catalogues from the normative map (design §3.2 plus files classified by P6.1).
 *
 * @var array<string, string>
 */
const CORE_ZONE_DUMP = [
    'Attributes/Api.php' => 'Foundation',
    'Attributes/Internal/SourceType.php' => 'Foundation',
    'Attributes/Internal/SurfaceDiagnostic.php' => 'Foundation',
    'Attributes/Internal/SurfaceReport.php' => 'Foundation',
    'Attributes/Internal/SurfaceScanner.php' => 'Foundation',
    'Attributes/Spi.php' => 'Foundation',
    'Attributes/SpiKind.php' => 'Foundation',
    'Audit/Internal/AuditObserver.php' => 'Audit',
    'Audit/Contracts/AuditEntry.php' => 'Audit',
    'Audit/Contracts/AuditLogger.php' => 'Audit',
    'Audit/Contracts/AuditRetention.php' => 'Audit',
    'Audit/Contracts/Auditable.php' => 'Audit',
    'Audit/CurrentActor.php' => 'Audit',
    'Audit/Enums/ActorType.php' => 'Foundation',
    'Audit/HasAuditLog.php' => 'Audit',
    'Audit/Internal/AuditLog.php' => 'Audit',
    'Audit/NullAuditRetention.php' => 'Audit',
    'Audit/Internal/PruneAuditLog.php' => 'Audit',
    'Audit/QueuedAuditLogger.php' => 'Audit',
    'Audit/Internal/RecordAuditEntry.php' => 'Audit',
    'Config/Contracts/ConfigReportSection.php' => 'Foundation',
    'Config/Exceptions/InvalidConfiguration.php' => 'Foundation',
    'Config/SecretRef.php' => 'Foundation',
    'Config/ViolationList.php' => 'Foundation',
    'Context/Contracts/AccountConnectionResolver.php' => 'TenancyContracts',
    'Context/Contracts/IdentityPasswordRehasher.php' => 'TenancyContracts',
    'Context/Contracts/ImpersonationService.php' => 'TenancyContracts',
    'Context/Contracts/TenancyManager.php' => 'TenancyContracts',
    'Context/Contracts/TenantContextResolver.php' => 'TenancyContracts',
    'Context/Contracts/TenantContextRestorer.php' => 'TenancyContracts',
    'Context/Contracts/TenantDatabaseLifecycle.php' => 'TenancyContracts',
    'Context/Contracts/TenantDatabaseProvisioner.php' => 'TenancyContracts',
    'Context/Data/AccountRef.php' => 'TenancyContracts',
    'Context/Data/TenantContext.php' => 'TenancyContracts',
    'Context/Data/WorkspaceRef.php' => 'TenancyContracts',
    'Context/Data/DatabaseConnectionConfig.php' => 'TenancyContracts',
    'Context/Enums/ImpersonationRestrictedAction.php' => 'TenancyContracts',
    'Context/Exceptions/CentralContextException.php' => 'TenancyContracts',
    'Context/Exceptions/ImpersonationRestrictedException.php' => 'TenancyContracts',
    'Context/Exceptions/ScopeContextMismatch.php' => 'TenancyContracts',
    'Context/GuardedTenantContextRestorer.php' => 'TenancyContracts',
    'Context/ImpersonationGuard.php' => 'TenancyContracts',
    'Context/Data/ImpersonationState.php' => 'TenancyContracts',
    'Context/NullImpersonationService.php' => 'TenancyContracts',
    'Context/NullTenancyManager.php' => 'TenancyContracts',
    'Context/Data/ProvisionParams.php' => 'TenancyContracts',
    'Context/SingleAccountResolver.php' => 'TenancyContracts',
    'Data/AllowAllDataScopeAuthorizer.php' => 'Foundation',
    'Data/ArrayDataScopeCodec.php' => 'Foundation',
    'Data/CallableDataScopeAuthorizer.php' => 'Foundation',
    'Data/Contracts/DataScopeAuthorizer.php' => 'Foundation',
    'Data/Contracts/DataScopeCodec.php' => 'Foundation',
    'Data/DataScope.php' => 'Foundation',
    'Data/Data/DataScopeAccessDecision.php' => 'Foundation',
    'Data/DenyDataScopeAuthorizer.php' => 'Foundation',
    'Data/Exceptions/InvalidScopePayload.php' => 'Foundation',
    'Data/Data/RecordRef.php' => 'Foundation',
    'Data/WriteToken.php' => 'Foundation',
    'Database/BaseModel.php' => 'Foundation',
    'Database/Enums/IdStrategy.php' => 'Foundation',
    'Database/HasConfiguredId.php' => 'Foundation',
    'Database/Schema/IdColumns.php' => 'Foundation',
    'Departments/Contracts/DepartmentRepository.php' => 'Departments',
    'Departments/DatabaseDepartmentRepository.php' => 'Departments',
    'Departments/Data/Department.php' => 'Departments',
    'Departments/Events/DepartmentMoved.php' => 'Departments',
    'Departments/Exceptions/CrossWorkspaceMoveException.php' => 'Departments',
    'Departments/Exceptions/DepartmentCycleException.php' => 'Departments',
    'Departments/Models/Department.php' => 'Departments',
    'Errors/Contracts/DescribesError.php' => 'Foundation',
    'Errors/Data/ErrorDescriptor.php' => 'Foundation',
    'Errors/Enums/ErrorCategory.php' => 'Foundation',
    'Errors/Enums/UnavailabilityReason.php' => 'Foundation',
    'Features/Contracts/FeatureFlagDefinition.php' => 'Features',
    'Features/Contracts/FeatureFlags.php' => 'Features',
    'Features/DatabaseFeatureFlags.php' => 'Features',
    'Features/Events/FeatureFlagToggled.php' => 'Features',
    'Features/Internal/Models/FeatureFlag.php' => 'Features',
    'Features/Internal/Models/FeatureOverride.php' => 'Features',
    'Messaging/Internal/Commands/PruneOutboxMessages.php' => 'Messaging',
    'Messaging/Internal/Commands/RelayOutbox.php' => 'Messaging',
    'Messaging/Internal/Commands/ShowDeliveryHealth.php' => 'Messaging',
    'Messaging/Contracts/Cancellable.php' => 'Foundation',
    'Messaging/Contracts/DurableMessagePublisher.php' => 'Messaging',
    'Messaging/Contracts/DurablePayload.php' => 'Foundation',
    'Messaging/Contracts/Inbox.php' => 'Messaging',
    'Messaging/Contracts/MessageContractRegistry.php' => 'Messaging',
    'Messaging/Contracts/MessagePublisher.php' => 'Messaging',
    'Messaging/Contracts/Outbox.php' => 'Messaging',
    'Messaging/Contracts/OutboxRelay.php' => 'Messaging',
    'Messaging/Contracts/PubSub.php' => 'Messaging',
    'Messaging/Contracts/TraceContextBridge.php' => 'Messaging',
    'Messaging/Contracts/WorkerRuntime.php' => 'Messaging',
    'Messaging/Data/DeliveryHealth.php' => 'Messaging',
    'Messaging/Data/LeasedMessage.php' => 'Messaging',
    'Messaging/Data/MessageContract.php' => 'Messaging',
    'Messaging/Data/MessageEnvelope.php' => 'Messaging',
    'Messaging/Data/MessagePayload.php' => 'Messaging',
    'Messaging/Internal/DatabasePollPubSub.php' => 'Messaging',
    'Messaging/Internal/DefaultWorkerRuntime.php' => 'Messaging',
    'Messaging/Enums/MessageClass.php' => 'Messaging',
    'Messaging/Enums/PiiClass.php' => 'Messaging',
    'Messaging/Enums/PubSubDriver.php' => 'Messaging',
    'Messaging/Events/DomainEvent.php' => 'Foundation',
    'Messaging/Exceptions/InboxRefused.php' => 'Messaging',
    'Messaging/Exceptions/InvalidMessage.php' => 'Messaging',
    'Messaging/Exceptions/OutboxLeaseLost.php' => 'Messaging',
    'Messaging/Exceptions/OutboxRefused.php' => 'Messaging',
    'Messaging/Exceptions/UnknownMessageContract.php' => 'Messaging',
    'Messaging/Internal/ArrayMessageContractRegistry.php' => 'Messaging',
    'Messaging/Internal/NullTraceContextBridge.php' => 'Messaging',
    'Messaging/Internal/OpenTelemetryTraceContextBridge.php' => 'Messaging',
    'Messaging/Internal/SqlDeliveryHealth.php' => 'Messaging',
    'Messaging/Internal/SqlInbox.php' => 'Messaging',
    'Messaging/Internal/SqlOutbox.php' => 'Messaging',
    'Messaging/Internal/SqlOutboxRelay.php' => 'Messaging',
    'Messaging/Internal/NullPubSub.php' => 'Messaging',
    'Messaging/PHPStan/Rules/DurablePayloadRule.php' => 'Foundation',
    'Messaging/Internal/PrunePubSubMessages.php' => 'Messaging',
    'Messaging/Internal/PubSubManager.php' => 'Messaging',
    'Messaging/Internal/RedisPubSub.php' => 'Messaging',
    'Messaging/Internal/SubscribeGuard.php' => 'Messaging',
    'Messaging/Testing/MessagingFixtures.php' => 'Messaging',
    'Modules/Contracts/ModuleRegistrar.php' => 'Foundation',
    'Observability/Contracts/OperationObserver.php' => 'Foundation',
    'Observability/Internal/MetricAttributes.php' => 'Foundation',
    'Observability/Internal/NullOperationObserver.php' => 'Foundation',
    'Observability/Internal/OpenTelemetryBridge.php' => 'Foundation',
    'Observability/Data/OperationCompleted.php' => 'Foundation',
    'Observability/Internal/OperationContext.php' => 'Foundation',
    'Observability/Data/OperationFailed.php' => 'Foundation',
    'Observability/Enums/OperationOutcome.php' => 'Foundation',
    'Observability/Data/OperationStarted.php' => 'Foundation',
    'Observability/Internal/PsrLogOperationObserver.php' => 'Foundation',
    'Settings/Contracts/SettingDefaultsProvider.php' => 'Settings',
    'Settings/Contracts/SettingsRepository.php' => 'Settings',
    'Settings/Contracts/SettingsScope.php' => 'Settings',
    'Settings/DatabaseSettingsRepository.php' => 'Settings',
    'Settings/Enums/SettingScope.php' => 'Settings',
    'Settings/Events/SettingChanged.php' => 'Settings',
    'Settings/Exceptions/SettingValueTooLargeException.php' => 'Settings',
    'Settings/Exceptions/UnsupportedScopeException.php' => 'Settings',
    'Settings/Internal/Models/Setting.php' => 'Settings',
    'Settings/NullSettingDefaultsProvider.php' => 'Settings',
    'Storage/Contracts/TenantStorage.php' => 'Storage',
    'Storage/Data/ExportArchiveRef.php' => 'Storage',
    'Storage/Internal/LocalTenantStorage.php' => 'Storage',
    'Storage/ScopedFilesystem.php' => 'Storage',
    'Storage/Data/StorageCapabilities.php' => 'Storage',
    'Storage/Exceptions/StorageCapabilityUnavailable.php' => 'Storage',
    'Storage/Exceptions/StorageOperationFailed.php' => 'Storage',
    'Time/FrozenClock.php' => 'Foundation',
    'Time/LaravelClock.php' => 'Foundation',
    'Time/SystemClock.php' => 'Foundation',
    'packages/intl/src/Money/MoneyCast.php' => 'Money',
    'packages/panels/src/SavedViews/Contracts/SavedViewRepository.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Contracts/ViewAccessPolicy.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Contracts/ViewSchemaProvider.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Data/SaveViewInput.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Data/SavedQuery.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Data/SavedViewRef.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Data/ViewAction.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Data/ViewContext.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Data/ViewField.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Data/ViewSchema.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Data/ViewScope.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Data/ViewSubject.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Internal/DenyViewAccessPolicy.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Internal/SqlSavedViewRepository.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Internal/UnavailableViewSchemaProvider.php' => 'SavedViews',
    'packages/panels/src/SavedViews/Internal/ViewConfigValidator.php' => 'SavedViews',
];

test('every core src file is classified into a zone or composition/testing', function (): void {
    expect(rz0Offenders())->toBe([])
        ->and(zoneOf('packages/core/src/Messaging/Internal/PubSubManager.php'))->toBe('Messaging')
        ->and(CORE_ZONE_ALLOWED['Foundation'])->toBe([])
        ->and(zoneOf('packages/core/src/Config/ViolationList.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Config/Exceptions/InvalidConfiguration.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Config/SecretRef.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Errors/Contracts/DescribesError.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Errors/Data/ErrorDescriptor.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Errors/Enums/ErrorCategory.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Errors/Enums/UnavailabilityReason.php'))->toBe('Foundation')
        ->and(coreZoneKind('packages/core/src/Config/CoreOptions.php'))->toBe('composition')
        ->and(coreZoneKind('packages/core/src/Modules/Contracts/PackageRegistrar.php'))->toBe('composition')
        ->and(coreZoneKind('packages/core/src/Modules/RegistersNothingOnBoot.php'))->toBe('composition');
});

test('zone files import only allowed CoreX zones (R-Z1)', function (): void {
    expect(rz1Offenders())->toBe([]);
});

test('foreign files do not import zone Internal (R-Z2)', function (): void {
    expect(rz2Offenders())->toBe([]);
});

test('R-Z1 planting in Foundation RecordRef names the file and symbol', function (): void {
    $line = coreZonePlant(
        'packages/core/src/Data/Data/RecordRef.php',
        'CoreX\\Panels\\SavedViews\\Internal\\SqlSavedViewRepository',
        rz1Offenders(...),
    );

    expect($line)->toContain('R-Z1')
        ->and($line)->toContain('RecordRef.php')
        ->and($line)->toContain('SqlSavedViewRepository');
});

test('R-Z1 planting in TenancyContracts AccountRef names the file and symbol', function (): void {
    $line = coreZonePlant(
        'packages/core/src/Context/Data/AccountRef.php',
        'CoreX\\Panels\\SavedViews\\Internal\\SqlSavedViewRepository',
        rz1Offenders(...),
    );

    expect($line)->toContain('R-Z1')
        ->and($line)->toContain('AccountRef.php')
        ->and($line)->toContain('SqlSavedViewRepository');
});

test('R-Z2 planting in ActorType names the file and symbol', function (): void {
    $line = coreZonePlant(
        'packages/core/src/Audit/Enums/ActorType.php',
        'CoreX\\Panels\\SavedViews\\Internal\\SqlSavedViewRepository',
        rz2Offenders(...),
    );

    expect($line)->toContain('R-Z2')
        ->and($line)->toContain('ActorType.php')
        ->and($line)->toContain('SqlSavedViewRepository');
});

test('R-Z0 planting of an unclassified error file names the path', function (): void {
    $relative = 'packages/core/src/Errors/Data/UnclassifiedError.php';
    $absolute = SourceScanner::repoRoot().'/'.$relative;
    $contents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Errors\Data;

final class UnclassifiedError
{
}
PHP;

    file_put_contents($absolute, $contents);

    try {
        $hits = array_values(array_filter(
            rz0Offenders(),
            static fn (string $line): bool => str_contains($line, 'Errors/Data/UnclassifiedError.php'),
        ));

        expect($hits)->not->toBe([])
            ->and($hits[0])->toContain('R-Z0')
            ->and($hits[0])->toContain('Errors/Data/UnclassifiedError.php');
    } finally {
        if (is_file($absolute)) {
            unlink($absolute);
        }
    }
});

test('R-Z0 planting of an unclassified Support file names the file', function (): void {
    $relative = 'packages/core/src/Config/Probe.php';
    $absolute = SourceScanner::repoRoot().'/'.$relative;
    $contents = <<<'PHP'
<?php

declare(strict_types=1);

namespace CoreX\Config;

final class Probe
{
}
PHP;

    file_put_contents($absolute, $contents);

    try {
        $hits = array_values(array_filter(
            rz0Offenders(),
            static fn (string $line): bool => str_contains($line, 'Config/Probe.php'),
        ));

        expect($hits)->not->toBe([])
            ->and($hits[0])->toContain('R-Z0')
            ->and($hits[0])->toContain('Probe.php');
    } finally {
        if (is_file($absolute)) {
            unlink($absolute);
        }
    }
});

/**
 * @return list<string>
 */
function corePhpFiles(): array
{
    $root = SourceScanner::packagePath('core', 'src');
    $prefix = SourceScanner::repoRoot().'/';
    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $files[] = str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    sort($files);

    return $files;
}

function zoneOf(string $relative): ?string
{
    $kind = coreZoneKind($relative);

    if ($kind === 'testing' || $kind === 'composition' || $kind === 'unclassified') {
        return null;
    }

    return $kind;
}

function coreZoneKind(string $relative): string
{
    $prefix = 'packages/core/src/';

    if (! str_starts_with($relative, $prefix)) {
        return 'unclassified';
    }

    $under = substr($relative, strlen($prefix));

    if (str_starts_with($under, 'Testing/')) {
        return 'testing';
    }

    if (in_array($relative, CORE_ZONE_COMPOSITION, true)) {
        return 'composition';
    }

    if (in_array($under, CORE_ZONE_FOUNDATION_SUPPORT, true)) {
        return 'Foundation';
    }

    if (isset(CORE_ZONE_DUMP[$under])) {
        return CORE_ZONE_DUMP[$under];
    }

    foreach (CORE_ZONE_PREFIX as $pathPrefix => $zone) {
        if (str_starts_with($under, $pathPrefix)) {
            return $zone;
        }
    }

    return CORE_ZONE_DUMP[$under] ?? 'unclassified';
}

function coreZoneIsExempt(string $relative): bool
{
    $kind = coreZoneKind($relative);

    return $kind === 'testing' || $kind === 'composition';
}

/**
 * @return list<string>
 */
function rz0Offenders(): array
{
    $offenders = [];

    foreach (corePhpFiles() as $relative) {
        if (coreZoneIsExempt($relative)) {
            continue;
        }

        if (zoneOf($relative) === null) {
            $offenders[] = 'R-Z0 '.$relative.' unclassified';
        }
    }

    return $offenders;
}

/**
 * @return list<string>
 */
function rz1Offenders(): array
{
    $offenders = [];

    foreach (corePhpFiles() as $relative) {
        $sourceZone = zoneOf($relative);

        if ($sourceZone === null) {
            continue;
        }

        $absolute = SourceScanner::repoRoot().'/'.$relative;

        foreach (coreZoneCoreXImports($absolute) as $import) {
            $target = coreZoneImportTarget($import);

            if ($target === null) {
                continue;
            }

            $targetKind = coreZoneKind($target);

            if ($targetKind === 'composition' || $targetKind === 'testing') {
                continue;
            }

            if (coreZoneExactImport($relative, $import)) {
                continue;
            }

            if ($targetKind === 'unclassified' || ! coreZoneImportAllowed($relative, $sourceZone, $targetKind)) {
                $offenders[] = 'R-Z1 '.$relative.' imports '.$import;
            }
        }
    }

    sort($offenders);

    return $offenders;
}

function coreZoneExactImport(string $relative, string $import): bool
{
    return in_array($import, CORE_ZONE_EXACT_IMPORTS[$relative] ?? [], true);
}

function coreZoneImportAllowed(string $relative, string $sourceZone, string $targetKind): bool
{
    if ($targetKind === $sourceZone) {
        return true;
    }

    if ($relative === CORE_ZONE_E_BASEMODEL && $targetKind === 'Audit') {
        return true;
    }

    return in_array($targetKind, CORE_ZONE_ALLOWED[$sourceZone], true);
}

/**
 * @return list<string>
 */
function rz2Offenders(): array
{
    $offenders = [];

    foreach (corePhpFiles() as $relative) {
        if (coreZoneKind($relative) === 'composition') {
            continue;
        }

        $absolute = SourceScanner::repoRoot().'/'.$relative;

        foreach (coreZoneCoreXImports($absolute) as $import) {
            if (preg_match('/^CoreX\\\\.+\\\\Internal\\\\/', $import) !== 1) {
                continue;
            }

            $target = coreZoneImportTarget($import);
            $zone = $target === null ? 'unclassified' : coreZoneKind($target);

            if (! coreZoneExactImport($relative, $import) && ($zone === 'unclassified' || ! coreZoneFileInZoneDirectories($relative, $zone))) {
                $offenders[] = 'R-Z2 '.$relative.' imports '.$import;
            }
        }
    }

    sort($offenders);

    return $offenders;
}

function coreZoneFileInZoneDirectories(string $relative, string $zone): bool
{
    return coreZoneKind($relative) === $zone;
}

/**
 * @return list<string>
 */
function coreZoneCoreXImports(string $absolute): array
{
    $contents = (string) file_get_contents($absolute);
    preg_match_all('/^\s*use\s+(?:function\s+|const\s+)?(CoreX\\\\[^;]+);/m', $contents, $matches);

    $imports = [];

    foreach ($matches[1] as $import) {
        $imports[] = trim(explode(' as ', $import, 2)[0]);
    }

    return $imports;
}

function coreZoneImportTarget(string $import): ?string
{
    if (! str_starts_with($import, 'CoreX\\')) {
        return null;
    }

    $rest = substr($import, strlen('CoreX\\'));

    return 'packages/core/src/'.str_replace('\\', '/', $rest).'.php';
}

/**
 * Insert one CoreX import, collect the matching diagnostic, then restore the file.
 *
 * @param  callable(): list<string>  $collect
 */
function coreZonePlant(string $relative, string $import, callable $collect): string
{
    $absolute = SourceScanner::repoRoot().'/'.$relative;
    $original = (string) file_get_contents($absolute);
    $needle = "declare(strict_types=1);\n";
    $count = 0;
    $planted = str_replace($needle, $needle."\nuse {$import};\n", $original, $count);

    if ($count !== 1) {
        throw new RuntimeException('R-Z planting could not insert a use into '.$relative);
    }

    file_put_contents($absolute, $planted);

    try {
        $symbol = substr($import, strrpos($import, '\\') + 1);
        $hits = array_values(array_filter(
            $collect(),
            static fn (string $line): bool => str_contains($line, basename($relative))
                && str_contains($line, $symbol),
        ));

        expect($hits)->not->toBe([]);

        return $hits[0];
    } finally {
        file_put_contents($absolute, $original);
    }
}
