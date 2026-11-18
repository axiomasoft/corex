<?php

declare(strict_types=1);

use CoreX\Audit\Internal\AuditLog;
use CoreX\Audit\Internal\AuditObserver;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Messaging\Events\DomainEvent;
use CoreX\Messaging\Internal\DomainEventDefaults;
use CoreX\Observability\Contracts\TracePropagation;
use CoreX\Observability\Data\OperationContext;
use CoreX\Panels\SavedViews\Internal\SqlSavedViewRepository;
use CoreX\Settings\Data\SettingsScope;
use CoreX\Settings\Enums\SettingScope;
use CoreX\Tests\Support\SourceScanner;
use Packman\Schema\Contracts\Migration\SchemaSets;
use Packman\Schema\Enums\SchemaLifecycle;
use Packman\Schema\Enums\SchemaScope;
use Packman\Schema\Exceptions\SchemaSetException;

// P6.2 — zone arch gate (D32/D101). Matrix is copied from the design, not read at runtime.
// Scheduling is the P07.5 kernel module and may import Foundation and tenancy contracts.
// Invocation (operation-handler contract, audit 2026-10-08 P-02) has the same allowance.
// PubSub/ is Messaging by path. ALLOWED['Foundation'] stays empty.

/**
 * @var array<string, list<string>>
 */
const CORE_ZONE_ALLOWED = [
    'Foundation' => [],
    'Database' => ['Foundation'],
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
    'Scheduling' => ['Foundation', 'TenancyContracts'],
    'Invocation' => ['Foundation', 'TenancyContracts'],
];

/** @var list<string> */
const CORE_ZONE_COMPOSITION = [
    'packages/core/src/Foundation/Laravel/ConsoleRegistrar.php',
    'packages/core/src/Foundation/Laravel/PackmanRegistrar.php',
    'packages/core/src/Foundation/Internal/PackmanActorResolver.php',
    'packages/core/src/Foundation/Internal/PackmanContextResolver.php',
    'packages/core/src/Foundation/Internal/PackmanFeatureFlags.php',
    'packages/core/src/Config/Laravel/CoreOptionsParser.php',
    'packages/core/src/Config/Internal/KernelConfigSection.php',
    'packages/core/src/CoreServiceProvider.php',
    'packages/core/src/Config/Contracts/RuntimeConfigCheck.php',
    'packages/core/src/Config/Laravel/RuntimeConfigGate.php',
    'packages/core/src/Config/Data/CoreOptions.php',
    'packages/core/src/Config/Internal/Commands/ValidateConfigCommand.php',
    'packages/core/src/Audit/Laravel/AuditRegistrar.php',
    'packages/core/src/Config/Laravel/ConfigRegistrar.php',
    'packages/core/src/Config/Laravel/ConfiguredImplementation.php',
    'packages/core/src/Config/Internal/ApplicationLocator.php',
    'packages/core/src/Context/Laravel/ContextRegistrar.php',
    'packages/core/src/Data/Laravel/DataRegistrar.php',
    'packages/core/src/Database/Laravel/DatabaseRegistrar.php',
    'packages/core/src/Departments/Laravel/DepartmentsRegistrar.php',
    'packages/core/src/Errors/Laravel/ErrorCatalogueFactory.php',
    'packages/core/src/Errors/Laravel/ErrorsRegistrar.php',
    'packages/core/src/Errors/Laravel/ProblemRegistryFactory.php',
    'packages/core/src/Features/Laravel/FeaturesRegistrar.php',
    'packages/core/src/Messaging/Laravel/MessagingRegistrar.php',
    'packages/core/src/Observability/Laravel/ObservabilityRegistrar.php',
    'packages/core/src/Settings/Laravel/SettingsRegistrar.php',
    'packages/core/src/Storage/Laravel/StorageRegistrar.php',
    'packages/core/src/Time/Laravel/TimeRegistrar.php',
];

const CORE_ZONE_E_BASEMODEL = 'packages/core/src/Database/Laravel/BaseModel.php';

/**
 * Closed public edges (D116). Not an ALLOWED widening: any other symbol stays R-Z1.
 *
 * @var array<string, list<string>>
 */
const CORE_ZONE_EXACT_IMPORTS = [
    'packages/core/src/Modules/Laravel/LifecycleRegistrar.php' => [
        SchemaSets::class,
        SchemaLifecycle::class,
        SchemaScope::class,
        SchemaSetException::class,
    ],
    'packages/core/src/Context/Internal/ImpersonationGuard.php' => [AuditLog::class],
    'packages/core/src/Data/Data/DataScope.php' => [
        TenantContext::class,
    ],
    'packages/core/src/Messaging/Events/DomainEvent.php' => [
        TenantContext::class,
        DomainEventDefaults::class,
    ],
    'packages/core/src/Messaging/Internal/DomainEventDefaults.php' => [
        TenantContext::class,
        TenantContextResolver::class,
    ],
    'packages/core/src/Features/Events/FeatureFlagToggled.php' => [
        SettingsScope::class,

        DomainEvent::class,
    ],
    'packages/core/src/Features/Internal/DatabaseFeatureFlags.php' => [
        SettingsScope::class,
        SettingScope::class,
    ],
    'packages/core/src/Context/Internal/GuardedTenantContextRestorer.php' => [
        TenantContext::class,
    ],
    'packages/core/src/Context/Internal/NullTenancyManager.php' => [
        AccountRef::class,
        TenantContext::class,
        WorkspaceRef::class,
    ],
    'packages/core/src/Context/Internal/SingleAccountResolver.php' => [
        AccountRef::class,
        TenantContext::class,
    ],
    'packages/core/src/Departments/Events/DepartmentMoved.php' => [
        DomainEvent::class,
    ],
    'packages/core/src/Features/Contracts/FeatureFlags.php' => [
        SettingsScope::class,
    ],
    'packages/core/src/Settings/Events/SettingChanged.php' => [
        DomainEvent::class,
    ],
    'packages/core/src/Messaging/Contracts/TraceContextBridge.php' => [
        OperationContext::class,
    ],
    'packages/core/src/Messaging/Internal/NullTraceContextBridge.php' => [
        OperationContext::class,
    ],
    'packages/core/src/Messaging/Internal/OpenTelemetryTraceContextBridge.php' => [
        TracePropagation::class,
        OperationContext::class,
    ],
    'packages/core/src/Modules/Internal/Audit/AuditCapabilityWiring.php' => [
        AuditObserver::class,
    ],
];

/**
 * Exact Foundation support types (D101). Any other unclassified Support file is R-Z0.
 *
 * @var list<string>
 */
const CORE_ZONE_FOUNDATION_SUPPORT = [
    'Errors/Contracts/ErrorCode.php',
    'Errors/Contracts/ProblemDefinitionProvider.php',
    'Errors/Contracts/ProblemRegistry.php',
    'Errors/Data/ProblemDefinition.php',
    'Errors/Enums/CoreErrorCode.php',
    'Errors/Exceptions/CodedException.php',
    'Errors/Exceptions/ProblemRegistryException.php',
    'Errors/Internal/CompiledProblemRegistry.php',
    'Errors/Tooling/ErrorSourceScanner.php',
    'Errors/Internal/PackageProblemDefinitions.php',
    'Errors/Internal/ProblemContribution.php',
    'Errors/Internal/ProblemRegistryRegistrar.php',
    'Errors/Laravel/ProblemDefinitions.php',
    'Config/Laravel/ConfigurationValidator.php',
    'Config/Exceptions/InvalidConfiguration.php',
    'Config/Data/SecretRef.php',
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
    'Invocation/' => 'Invocation',
    'Messaging/' => 'Messaging',
    'Modules/' => 'Modules',
    'Money/' => 'Money',
    'Scheduling/' => 'Scheduling',
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
    'Database/Contracts/SchemaMigrator.php' => 'Database',
    'Database/Contracts/SchemaSetProvider.php' => 'Database',
    'Database/Contracts/SchemaSets.php' => 'Database',
    'Database/Data/SchemaSet.php' => 'Database',
    'Database/Data/SchemaSetStatus.php' => 'Database',
    'Database/Enums/SchemaLifecycle.php' => 'Database',
    'Database/Enums/SchemaScope.php' => 'Database',
    'Database/Exceptions/SchemaSetException.php' => 'Database',
    'Database/Internal/Commands/SchemaMigrateCommand.php' => 'Database',
    'Database/Internal/Commands/SchemaStatusCommand.php' => 'Database',
    'Database/Internal/CoreSchemaSets.php' => 'Database',
    'Database/Internal/LaravelSchemaMigrator.php' => 'Database',
    'Database/Internal/TaggedSchemaSets.php' => 'Database',
    'Database/Testing/MigratesSchemaSets.php' => 'Database',

    'Observability/Contracts/TracePropagation.php' => 'Foundation',
    'Messaging/PHPStan/DurablePayloadTypeChecker.php' => 'Foundation',
    'Messaging/PHPStan/Rules/DurablePayloadPromotedPropertyRule.php' => 'Foundation',
    'Attributes/Api.php' => 'Foundation',
    'Attributes/Tooling/SourceType.php' => 'Foundation',
    'Attributes/Tooling/SurfaceDiagnostic.php' => 'Foundation',
    'Attributes/Tooling/SurfaceReport.php' => 'Foundation',
    'Attributes/Tooling/SurfaceScanner.php' => 'Foundation',
    'Attributes/Spi.php' => 'Foundation',
    'Attributes/SpiKind.php' => 'Foundation',
    'Audit/Internal/AuditObserver.php' => 'Audit',
    'Audit/Data/AuditEntry.php' => 'Audit',
    'Audit/Contracts/AuditLogger.php' => 'Audit',
    'Audit/Contracts/AuditRetention.php' => 'Audit',
    'Audit/Contracts/Auditable.php' => 'Audit',
    'Audit/Internal/CurrentActor.php' => 'Audit',
    'Audit/Enums/ActorType.php' => 'Foundation',
    'Audit/Laravel/HasAuditLog.php' => 'Audit',
    'Audit/Internal/AuditLog.php' => 'Audit',
    'Audit/Internal/NullAuditRetention.php' => 'Audit',
    'Audit/Internal/PruneAuditLog.php' => 'Audit',
    'Audit/Internal/QueuedAuditLogger.php' => 'Audit',
    'Audit/Internal/RecordAuditEntry.php' => 'Audit',
    'Config/Contracts/ConfigReportSection.php' => 'Foundation',
    'Config/Exceptions/InvalidConfiguration.php' => 'Foundation',
    'Config/Data/SecretRef.php' => 'Foundation',
    'Config/Laravel/ConfigurationValidator.php' => 'Foundation',
    'Context/Contracts/AccountConnectionResolver.php' => 'TenancyContracts',
    'Context/Contracts/IdentityPasswordRehasher.php' => 'TenancyContracts',
    'Context/Contracts/ImpersonationService.php' => 'TenancyContracts',
    'Context/Contracts/TenancyManager.php' => 'TenancyContracts',
    'Context/Contracts/TenantContextResolver.php' => 'TenancyContracts',
    'Context/Contracts/TenantContextRestorer.php' => 'TenancyContracts',
    'Context/Contracts/TenantDatabaseLifecycle.php' => 'TenancyContracts',
    'Context/Contracts/TenantDatabaseProvisioner.php' => 'TenancyContracts',
    'Context/Data/AccountRef.php' => 'TenancyContracts',
    'Context/Data/DatabaseConnectionConfig.php' => 'TenancyContracts',
    'Context/Data/TenantContext.php' => 'TenancyContracts',
    'Context/Data/WorkspaceRef.php' => 'TenancyContracts',
    'Context/Enums/ImpersonationRestrictedAction.php' => 'TenancyContracts',
    'Context/Exceptions/CentralContextException.php' => 'TenancyContracts',
    'Context/Exceptions/ImpersonationRestrictedException.php' => 'TenancyContracts',
    'Context/Exceptions/ScopeContextMismatch.php' => 'TenancyContracts',
    'Context/Internal/GuardedTenantContextRestorer.php' => 'TenancyContracts',
    'Context/Internal/ImpersonationGuard.php' => 'TenancyContracts',
    'Context/Data/ImpersonationState.php' => 'TenancyContracts',
    'Context/Internal/NullImpersonationService.php' => 'TenancyContracts',
    'Context/Internal/NullTenancyManager.php' => 'TenancyContracts',
    'Context/Data/ProvisionParams.php' => 'TenancyContracts',
    'Context/Internal/SingleAccountResolver.php' => 'TenancyContracts',
    'Data/Internal/AllowAllDataScopeAuthorizer.php' => 'Foundation',
    'Data/Internal/ArrayDataScopeCodec.php' => 'Foundation',
    'Data/Internal/CallableDataScopeAuthorizer.php' => 'Foundation',
    'Data/Contracts/DataScopeAuthorizer.php' => 'Foundation',
    'Data/Contracts/DataScopeCodec.php' => 'Foundation',
    'Data/Data/DataScope.php' => 'Foundation',
    'Data/Data/DataScopeAccessDecision.php' => 'Foundation',
    'Data/Internal/DenyDataScopeAuthorizer.php' => 'Foundation',
    'Data/Exceptions/InvalidScopePayload.php' => 'Foundation',
    'Data/Data/RecordRef.php' => 'Foundation',
    'Data/Data/WriteToken.php' => 'Foundation',
    'Database/Laravel/BaseModel.php' => 'Foundation',
    'Departments/Contracts/DepartmentRepository.php' => 'Departments',
    'Departments/Internal/DatabaseDepartmentRepository.php' => 'Departments',
    'Departments/Data/Department.php' => 'Departments',
    'Departments/Events/DepartmentMoved.php' => 'Departments',
    'Departments/Exceptions/CrossWorkspaceMoveException.php' => 'Departments',
    'Departments/Exceptions/DepartmentCycleException.php' => 'Departments',
    'Departments/Internal/Models/Department.php' => 'Departments',
    'Errors/Contracts/DescribesError.php' => 'Foundation',
    'Errors/Data/ErrorDescriptor.php' => 'Foundation',
    'Errors/Enums/ErrorCategory.php' => 'Foundation',
    'Errors/Enums/UnavailabilityReason.php' => 'Foundation',
    'Features/Data/FeatureFlagDefinition.php' => 'Features',
    'Features/Contracts/FeatureFlags.php' => 'Features',
    'Features/Internal/DatabaseFeatureFlags.php' => 'Features',
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
    'Messaging/Internal/DomainEventDefaults.php' => 'Foundation',
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
    'Messaging/Internal/RedisPubSub.php' => 'Messaging',
    'Messaging/Internal/SubscribeGuard.php' => 'Messaging',
    'Messaging/Testing/MessagingFixtures.php' => 'Messaging',
    'Modules/Contracts/ModuleRegistrar.php' => 'Foundation',
    'Observability/Contracts/OperationObserver.php' => 'Foundation',
    'Observability/Internal/MetricAttributes.php' => 'Foundation',
    'Observability/Internal/NullOperationObserver.php' => 'Foundation',
    'Observability/Internal/OpenTelemetryBridge.php' => 'Foundation',
    'Observability/Data/OperationCompleted.php' => 'Foundation',
    'Observability/Data/OperationContext.php' => 'Foundation',
    'Observability/Data/OperationFailed.php' => 'Foundation',
    'Observability/Enums/OperationOutcome.php' => 'Foundation',
    'Observability/Data/OperationStarted.php' => 'Foundation',
    'Observability/Internal/PsrLogOperationObserver.php' => 'Foundation',
    'Settings/Contracts/SettingDefaultsProvider.php' => 'Settings',
    'Settings/Contracts/SettingsRepository.php' => 'Settings',
    'Settings/Data/SettingsScope.php' => 'Settings',
    'Settings/Internal/DatabaseSettingsRepository.php' => 'Settings',
    'Settings/Enums/SettingScope.php' => 'Settings',
    'Settings/Events/SettingChanged.php' => 'Settings',
    'Settings/Exceptions/SettingValueTooLargeException.php' => 'Settings',
    'Settings/Exceptions/UnsupportedScopeException.php' => 'Settings',
    'Settings/Internal/Models/Setting.php' => 'Settings',
    'Settings/Internal/NullSettingDefaultsProvider.php' => 'Settings',
    'Storage/Contracts/TenantStorage.php' => 'Storage',
    'Storage/Data/ExportArchiveRef.php' => 'Storage',
    'Storage/Internal/LocalTenantStorage.php' => 'Storage',
    'Storage/Internal/ScopedFilesystem.php' => 'Storage',
    'Storage/Data/StorageCapabilities.php' => 'Storage',
    'Storage/Exceptions/StorageCapabilityUnavailable.php' => 'Storage',
    'Storage/Exceptions/StorageOperationFailed.php' => 'Storage',
    'Time/Testing/FrozenClock.php' => 'Foundation',
    'Time/Laravel/LaravelClock.php' => 'Foundation',
    'Time/Laravel/SystemClock.php' => 'Foundation',
    'packages/intl/src/Money/Laravel/MoneyCast.php' => 'Money',
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
        ->and(zoneOf('packages/core/src/Messaging/Internal/RedisPubSub.php'))->toBe('Messaging')
        ->and(CORE_ZONE_ALLOWED['Foundation'])->toBe([])
        ->and(zoneOf('packages/core/src/Config/Laravel/ConfigurationValidator.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Config/Exceptions/InvalidConfiguration.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Config/Data/SecretRef.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Errors/Contracts/DescribesError.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Errors/Data/ErrorDescriptor.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Errors/Enums/ErrorCategory.php'))->toBe('Foundation')
        ->and(zoneOf('packages/core/src/Errors/Enums/UnavailabilityReason.php'))->toBe('Foundation')
        ->and(coreZoneKind('packages/core/src/Config/Data/CoreOptions.php'))->toBe('composition')
        ->and(coreZoneKind('packages/core/src/Foundation/Laravel/PackmanRegistrar.php'))->toBe('composition');
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
        SqlSavedViewRepository::class,
        rz1Offenders(...),
    );

    expect($line)->toContain('R-Z1')
        ->and($line)->toContain('RecordRef.php')
        ->and($line)->toContain('SqlSavedViewRepository');
});

test('R-Z1 planting in TenancyContracts AccountRef names the file and symbol', function (): void {
    $line = coreZonePlant(
        'packages/core/src/Context/Data/AccountRef.php',
        SqlSavedViewRepository::class,
        rz1Offenders(...),
    );

    expect($line)->toContain('R-Z1')
        ->and($line)->toContain('AccountRef.php')
        ->and($line)->toContain('SqlSavedViewRepository');
});

test('R-Z2 planting in ActorType names the file and symbol', function (): void {
    $line = coreZonePlant(
        'packages/core/src/Audit/Enums/ActorType.php',
        SqlSavedViewRepository::class,
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
