# PHP API surface

Generated from source by `scripts/php-api.php --write`; schema `php-api/1`.

Attributes own membership. Members belong to this package; direct extends/implements/uses identify external sources. Full member signatures are in each package's `resources/contracts/php-api.json`.

## axiomasoft/corex

[Member manifest](../../packages/core/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Audit\Contracts\AuditLogger` | spi-implement | 1.0 |
| `CoreX\Audit\Contracts\AuditRetention` | spi-implement | 1.0 |
| `CoreX\Audit\Contracts\Auditable` | spi-implement | 1.0 |
| `CoreX\Audit\Contracts\CurrentActor` | spi-call | 1.0 |
| `CoreX\Audit\Contracts\SynchronousAuditWriter` | spi-implement | 1.0 |
| `CoreX\Audit\Data\AuditEntry` | api | 1.0 |
| `CoreX\Audit\Enums\ActorType` | api | 1.0 |
| `CoreX\Audit\Laravel\AuditRegistrar` | spi-call | 1.0 |
| `CoreX\Audit\Laravel\AuditRegistration` | spi-call | 1.0 |
| `CoreX\Audit\Laravel\AuditWriteExemption` | spi-call | 1.0 |
| `CoreX\Audit\Laravel\HasAuditLog` | spi-call | 1.0 |
| `CoreX\Config\Data\CoreOptions` | api | 1.0 |
| `CoreX\Config\Laravel\ConfigRegistrar` | spi-call | 1.0 |
| `CoreX\Config\Laravel\CoreOptionsParser` | spi-call | 1.0 |
| `CoreX\Context\Contracts\AccountConnectionResolver` | spi-implement | 1.0 |
| `CoreX\Context\Contracts\CentralWorkRunner` | spi-implement | 2.0.0 |
| `CoreX\Context\Contracts\ControlStoreIsolation` | spi-implement | 2.0.0 |
| `CoreX\Context\Contracts\CurrentWorkScope` | spi-implement | 2.0.0 |
| `CoreX\Context\Contracts\DataScopeFactory` | spi-implement | 2.0.0 |
| `CoreX\Context\Contracts\IdentityPasswordRehasher` | spi-implement | 1.0 |
| `CoreX\Context\Contracts\ImpersonationGuard` | spi-call | 1.0 |
| `CoreX\Context\Contracts\ImpersonationService` | spi-implement | 1.0 |
| `CoreX\Context\Contracts\NamedWorkPrincipal` | spi-implement | 2.0.0 |
| `CoreX\Context\Contracts\PhysicalBindingVerifier` | spi-implement | 2.0.0 |
| `CoreX\Context\Contracts\Principal` | spi-implement | 1.0 |
| `CoreX\Context\Contracts\ScopeAdmission` | spi-implement | 2.0.0 |
| `CoreX\Context\Contracts\ScopeAuthority` | spi-implement | 2.0.0 |
| `CoreX\Context\Contracts\StoredWorkIssuer` | spi-call | 2.0.0 |
| `CoreX\Context\Contracts\StoredWorkRunner` | spi-call | 2.0.0 |
| `CoreX\Context\Contracts\TenancyManager` | spi-implement | 1.0 |
| `CoreX\Context\Contracts\TenantContextResolver` | spi-implement | 1.0 |
| `CoreX\Context\Contracts\TenantDatabaseLifecycle` | spi-implement | 1.0 |
| `CoreX\Context\Contracts\TenantDatabaseProvisioner` | spi-implement | 1.0 |
| `CoreX\Context\Contracts\WorkPrincipalAuthority` | spi-implement | 2.0.0 |
| `CoreX\Context\Contracts\WorkScopeDirectory` | spi-implement | 2.0.0 |
| `CoreX\Context\Contracts\WorkScopeRunner` | spi-implement | 2.0.0 |
| `CoreX\Context\Data\AccountRef` | api | 1.0 |
| `CoreX\Context\Data\AdmissionTicket` | api | 2.0.0 |
| `CoreX\Context\Data\AttemptEvidence` | api | 2.0.0 |
| `CoreX\Context\Data\DatabaseConnectionConfig` | api | 1.0 |
| `CoreX\Context\Data\ImpersonationState` | api | 1.0 |
| `CoreX\Context\Data\OperationKey` | api | 2.0.0 |
| `CoreX\Context\Data\PhysicalBinding` | api | 2.0.0 |
| `CoreX\Context\Data\ProvisionParams` | api | 1.0 |
| `CoreX\Context\Data\ScopePrincipal` | api | 2.0.0 |
| `CoreX\Context\Data\StoredWorkProof` | api | 2.0.0 |
| `CoreX\Context\Data\TenantContext` | api | 1.0 |
| `CoreX\Context\Data\WorkspaceRef` | api | 1.0 |
| `CoreX\Context\Enums\AdmissionKind` | api | 2.0.0 |
| `CoreX\Context\Enums\ImpersonationRestrictedAction` | api | 1.0 |
| `CoreX\Context\Enums\PrincipalType` | api | 1.0 |
| `CoreX\Context\Exceptions\CentralContextException` | api | 1.0 |
| `CoreX\Context\Exceptions\ImpersonationRestrictedException` | api | 1.0 |
| `CoreX\Context\Exceptions\ScopeAuthorityUnavailable` | api | 2.0.0 |
| `CoreX\Context\Exceptions\ScopeContextMismatch` | api | 1.0 |
| `CoreX\Context\Exceptions\ScopeStale` | api | 2.0.0 |
| `CoreX\Context\Laravel\ContextRegistrar` | spi-call | 1.0 |
| `CoreX\Context\Laravel\DatabaseScopeControl` | spi-call | 2.0.0 |
| `CoreX\Context\Laravel\ScopeControlSchema` | spi-call | 2.0.0 |
| `CoreX\Context\Laravel\ScopeOptions` | api | 2.0.0 |
| `CoreX\Context\Laravel\WorkAuthentication` | spi-call | 2.0.0 |
| `CoreX\Context\Laravel\WorkQueueHandlers` | spi-call | 2.0.0 |
| `CoreX\Context\Laravel\WorkspaceScopeProvider` | spi-implement | 1.0 |
| `CoreX\CoreServiceProvider` | spi-call | 1.0 |
| `CoreX\Data\Contracts\DataScopeAuthorizer` | spi-implement | 2.0.0 |
| `CoreX\Data\Contracts\DataScopeCodec` | spi-implement | 1.0 |
| `CoreX\Data\Contracts\FieldUsePolicy` | spi-implement | 2.0.0 |
| `CoreX\Data\Data\DataScope` | api | 2.0.0 |
| `CoreX\Data\Data\DataScopeAccessDecision` | api | 1.0 |
| `CoreX\Data\Data\EntityHandle` | api | 2.0.0 |
| `CoreX\Data\Data\FieldSelection` | api | 2.0.0 |
| `CoreX\Data\Data\FieldUsePlan` | api | 2.0.0 |
| `CoreX\Data\Data\FieldUseRequest` | api | 2.0.0 |
| `CoreX\Data\Data\JsonDocument` | api | 1.0 |
| `CoreX\Data\Data\RecordRef` | api | 1.0 |
| `CoreX\Data\Data\ScopeIdentity` | api | 2.0.0 |
| `CoreX\Data\Data\WriteToken` | api | 1.0 |
| `CoreX\Data\Enums\FieldUse` | api | 2.0.0 |
| `CoreX\Data\Enums\FieldUsePlanKind` | api | 2.0.0 |
| `CoreX\Data\Exceptions\InvalidScopePayload` | spi-call | 1.0 |
| `CoreX\Data\Laravel\DataRegistrar` | spi-call | 1.0 |
| `CoreX\Data\Laravel\JsonDocuments` | spi-call | 1.0 |
| `CoreX\Database\Laravel\BaseModel` | spi-implement | 1.0 |
| `CoreX\Database\Laravel\DatabaseRegistrar` | spi-call | 1.0 |
| `CoreX\Departments\Contracts\DepartmentRepository` | api | 1.0 |
| `CoreX\Departments\Data\Department` | api | 1.0 |
| `CoreX\Departments\Events\DepartmentMoved` | spi-call | 1.0 |
| `CoreX\Departments\Exceptions\CrossWorkspaceMoveException` | api | 1.0 |
| `CoreX\Departments\Exceptions\DepartmentCycleException` | api | 1.0 |
| `CoreX\Departments\Laravel\DepartmentQueries` | spi-call | 1.0 |
| `CoreX\Departments\Laravel\DepartmentsRegistrar` | spi-call | 1.0 |
| `CoreX\Errors\Enums\CoreErrorCode` | api | 1.0 |
| `CoreX\Features\Contracts\FeatureFlags` | api | 1.0 |
| `CoreX\Features\Data\FeatureFlagDefinition` | api | 1.0 |
| `CoreX\Features\Events\FeatureFlagToggled` | spi-call | 1.0 |
| `CoreX\Features\Laravel\FeaturesRegistrar` | spi-call | 1.0 |
| `CoreX\Foundation\Laravel\ConsoleRegistrar` | spi-call | 1.0 |
| `CoreX\Foundation\Laravel\PackmanRegistrar` | spi-call | 1.0 |
| `CoreX\Invocation\Contracts\OperationHandler` | spi-implement | 1.0 |
| `CoreX\Invocation\Contracts\RequestContext` | spi-implement | 1.0 |
| `CoreX\Invocation\Data\InvocationMetadata` | api | 1.0 |
| `CoreX\Invocation\OperationDefinitionValidator` | spi-call | 1.0 |
| `CoreX\Messaging\Contracts\Cancellable` | spi-implement | 1.0 |
| `CoreX\Messaging\Contracts\CloudEventCodec` | api | 1.0 |
| `CoreX\Messaging\Contracts\DurableMessagePublisher` | spi-implement | 1.0 |
| `CoreX\Messaging\Contracts\DurablePayload` | spi-implement | 1.0 |
| `CoreX\Messaging\Contracts\Inbox` | spi-implement | 1.0 |
| `CoreX\Messaging\Contracts\MessageContractRegistry` | spi-implement | 1.0 |
| `CoreX\Messaging\Contracts\MessagePublisher` | spi-implement | 1.0 |
| `CoreX\Messaging\Contracts\Outbox` | spi-implement | 1.0 |
| `CoreX\Messaging\Contracts\OutboxRelay` | spi-implement | 1.0 |
| `CoreX\Messaging\Contracts\PubSub` | spi-implement | 1.0 |
| `CoreX\Messaging\Contracts\TraceContextBridge` | spi-implement | 1.0 |
| `CoreX\Messaging\Contracts\WorkerRuntime` | spi-implement | 1.0 |
| `CoreX\Messaging\Data\DeliveryHealth` | api | 1.0 |
| `CoreX\Messaging\Data\LeasedMessage` | api | 1.0 |
| `CoreX\Messaging\Data\MessageContract` | api | 1.0 |
| `CoreX\Messaging\Data\MessageEnvelope` | api | 1.0 |
| `CoreX\Messaging\Data\MessagePayload` | api | 1.0 |
| `CoreX\Messaging\Enums\MessageClass` | api | 1.0 |
| `CoreX\Messaging\Enums\PiiClass` | api | 1.0 |
| `CoreX\Messaging\Enums\PubSubDriver` | api | 1.0 |
| `CoreX\Messaging\Events\DomainEvent` | spi-implement | 1.0 |
| `CoreX\Messaging\Exceptions\InboxRefused` | api | 1.0 |
| `CoreX\Messaging\Exceptions\InvalidMessage` | api | 1.0 |
| `CoreX\Messaging\Exceptions\OutboxLeaseLost` | api | 1.0 |
| `CoreX\Messaging\Exceptions\OutboxRefused` | api | 1.0 |
| `CoreX\Messaging\Exceptions\UnknownMessageContract` | api | 1.0 |
| `CoreX\Messaging\Laravel\InboxRetention` | spi-call | 1.0 |
| `CoreX\Messaging\Laravel\MessagingRegistrar` | spi-call | 1.0 |
| `CoreX\Messaging\Laravel\PubSubAdapters` | spi-call | 1.0 |
| `CoreX\Messaging\PHPStan\Rules\DurablePayloadPromotedPropertyRule` | spi-implement | 1.0 |
| `CoreX\Messaging\PHPStan\Rules\DurablePayloadRule` | spi-call | 1.0 |
| `CoreX\Messaging\Testing\MessagingFixtures` | spi-call | 1.0 |
| `CoreX\Observability\Contracts\OperationObserver` | spi-implement | 1.0 |
| `CoreX\Observability\Contracts\TracePropagation` | spi-call | 1.0 |
| `CoreX\Observability\Data\OperationCompleted` | api | 1.0 |
| `CoreX\Observability\Data\OperationContext` | api | 1.0 |
| `CoreX\Observability\Data\OperationFailed` | api | 1.0 |
| `CoreX\Observability\Data\OperationStarted` | api | 1.0 |
| `CoreX\Observability\Enums\OperationOutcome` | api | 1.0 |
| `CoreX\Observability\Laravel\ObservabilityRegistrar` | spi-call | 1.0 |
| `CoreX\Scheduling\Contracts\DueWakeupHandler` | spi-implement | 1.0 |
| `CoreX\Scheduling\Contracts\DueWakeupSource` | spi-implement | 1.0 |
| `CoreX\Scheduling\Contracts\DueWakeupSweeper` | spi-implement | 1.0 |
| `CoreX\Scheduling\Contracts\WakeupScheduleHint` | spi-implement | 1.0 |
| `CoreX\Scheduling\Contracts\WakeupScheduler` | spi-implement | 2.0.0 |
| `CoreX\Scheduling\Data\CancelResult` | api | 1.0 |
| `CoreX\Scheduling\Data\ScheduleResult` | api | 2.0.0 |
| `CoreX\Scheduling\Data\SweepResult` | api | 1.0 |
| `CoreX\Scheduling\Data\WakeupDelivery` | api | 2.0.0 |
| `CoreX\Scheduling\Data\WakeupHandlerDefinition` | api | 2.0.0 |
| `CoreX\Scheduling\Data\WakeupIdentity` | api | 2.0.0 |
| `CoreX\Scheduling\Data\WakeupRequest` | api | 2.0.0 |
| `CoreX\Scheduling\Data\WakeupWindow` | api | 1.0 |
| `CoreX\Settings\Contracts\SettingsRepository` | api | 1.0 |
| `CoreX\Settings\Data\SettingsScope` | api | 1.0 |
| `CoreX\Settings\Enums\SettingScope` | api | 1.0 |
| `CoreX\Settings\Events\SettingChanged` | spi-call | 1.0 |
| `CoreX\Settings\Exceptions\SettingValueTooLargeException` | api | 1.0 |
| `CoreX\Settings\Exceptions\UnsupportedScopeException` | api | 1.0 |
| `CoreX\Settings\Laravel\SettingsRegistrar` | spi-call | 1.0 |
| `CoreX\Storage\Contracts\TenantStorage` | spi-implement | 1.0 |
| `CoreX\Storage\Data\ExportArchiveRef` | api | 1.0 |
| `CoreX\Storage\Data\StorageCapabilities` | api | 1.0 |
| `CoreX\Storage\Exceptions\StorageCapabilityUnavailable` | api | 1.0 |
| `CoreX\Storage\Exceptions\StorageOperationFailed` | api | 1.0 |
| `CoreX\Storage\Laravel\ScopedFilesystemFactory` | spi-call | 1.0 |
| `CoreX\Storage\Laravel\StorageRegistrar` | spi-call | 1.0 |
| `CoreX\Time\Laravel\LaravelClock` | spi-call | 1.0 |
| `CoreX\Time\Laravel\SystemClock` | spi-call | 1.0 |
| `CoreX\Time\Laravel\TimeRegistrar` | spi-call | 1.0 |
| `CoreX\Time\Testing\FrozenClock` | spi-call | 1.0 |

## axiomasoft/corex-access

[Member manifest](../../packages/access/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Access\AccessServiceProvider` | spi-call | 1.0 |
| `CoreX\Access\Access\Laravel\PrincipalIdentity` | spi-call | 1.0 |
| `CoreX\Access\Contracts\AccessChecker` | spi-implement | 1.0 |
| `CoreX\Access\Contracts\RoleMatrixCompiler` | spi-implement | 1.0 |
| `CoreX\Access\Data\RecordContext` | api | 1.0 |
| `CoreX\Access\Enums\Scope` | api | 1.0 |
| `CoreX\Access\Errors\Enums\AccessErrorCode` | api | 1.0 |
| `CoreX\Access\Exceptions\ExactVisibilityUnavailable` | api | 1.0 |
| `CoreX\Access\Exceptions\ScopeCapabilityMismatch` | api | 1.0 |
| `CoreX\Access\Identity\Laravel\PrincipalUserProvider` | spi-call | 1.0 |
| `CoreX\Access\Laravel\AccessBindingsRegistrar` | spi-call | 1.0 |
| `CoreX\Access\Laravel\AccessIntegrationRegistrar` | spi-call | 1.0 |
| `CoreX\Access\Laravel\AuthOptions` | spi-call | 1.0 |
| `CoreX\Access\Laravel\ScopeResolver` | spi-implement | 1.0 |
| `CoreX\Access\Matrix\Contracts\EntityActivity` | spi-call | 1.0 |
| `CoreX\Access\Matrix\Contracts\ScopeMapVersions` | spi-call | 1.0 |
| `CoreX\Access\Matrix\Events\RoleMatrixCompiled` | api | 1.0 |
| `CoreX\Access\Matrix\Laravel\MatrixPortBindings` | spi-call | 1.0 |
| `CoreX\Access\Roles\Data\RoleMatrixEntry` | api | 1.0 |
| `CoreX\Access\Roles\Data\RoleView` | api | 1.0 |
| `CoreX\Access\Roles\DirectGrants` | api | 1.0 |
| `CoreX\Access\Roles\Laravel\RoleMatrixData` | spi-call | 1.0 |
| `CoreX\Access\Roles\Laravel\SystemRoleImmutable` | spi-call | 1.0 |
| `CoreX\Access\Roles\RoleAdministration` | api | 1.0 |

## axiomasoft/corex-custom-fields

[Member manifest](../../packages/custom-fields/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\CustomFields\Contracts\AttributeFilter` | spi-implement | 1.0 |
| `CoreX\CustomFields\Contracts\AttributeStore` | spi-implement | 1.0 |
| `CoreX\CustomFields\Contracts\AttributeStoreResolver` | spi-implement | 1.0 |
| `CoreX\CustomFields\Contracts\FieldCatalogue` | spi-implement | 1.0 |
| `CoreX\CustomFields\Contracts\SchemaOperations` | spi-implement | 1.0 |
| `CoreX\CustomFields\CustomFieldsServiceProvider` | spi-call | 1.0 |
| `CoreX\CustomFields\Data\AttributeDefinition` | api | 1.0 |
| `CoreX\CustomFields\Data\AttributeId` | api | 1.0 |
| `CoreX\CustomFields\Data\AttributeLifecycle` | api | 1.0 |
| `CoreX\CustomFields\Data\AttributeStoragePolicy` | api | 1.0 |
| `CoreX\CustomFields\Data\AttributeTables` | api | 1.0 |
| `CoreX\CustomFields\Data\AttributeWriteResult` | api | 1.0 |
| `CoreX\CustomFields\Data\DriftReport` | api | 1.0 |
| `CoreX\CustomFields\Data\FieldSnapshot` | api | 1.0 |
| `CoreX\CustomFields\Data\FilterNode` | api | 1.0 |
| `CoreX\CustomFields\Data\FilterPlan` | api | 1.0 |
| `CoreX\CustomFields\Data\IndexPolicy` | api | 1.0 |
| `CoreX\CustomFields\Data\OperationRef` | api | 1.0 |
| `CoreX\CustomFields\Data\RecordSelection` | api | 1.0 |
| `CoreX\CustomFields\Data\SchemaChange` | api | 1.0 |
| `CoreX\CustomFields\Data\SchemaOperationId` | api | 1.0 |
| `CoreX\CustomFields\Data\SchemaReceipt` | api | 1.0 |
| `CoreX\CustomFields\Data\ValueBag` | api | 1.0 |
| `CoreX\CustomFields\Data\ValueBatch` | api | 1.0 |
| `CoreX\CustomFields\Data\ValuePatch` | api | 1.0 |
| `CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode` | api | 1.0 |
| `CoreX\CustomFields\Exceptions\InvalidAttributeTables` | api | 1.0 |

## axiomasoft/corex-import-export

[Member manifest](../../packages/import-export/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\ImportExport\Contracts\CancellationSignal` | spi-implement | 1.0 |
| `CoreX\ImportExport\Contracts\ExportRunner` | spi-implement | 1.0 |
| `CoreX\ImportExport\Contracts\ExportSource` | spi-implement | 1.0 |
| `CoreX\ImportExport\Contracts\ImportExportFactory` | spi-call | 1.0 |
| `CoreX\ImportExport\Contracts\RecordMapper` | spi-implement | 1.0 |
| `CoreX\ImportExport\Contracts\TransferResultReader` | spi-implement | 1.0 |
| `CoreX\ImportExport\Contracts\TransferRunner` | spi-implement | 1.0 |
| `CoreX\ImportExport\Contracts\TransferSink` | spi-implement | 1.0 |
| `CoreX\ImportExport\Contracts\TransferSource` | spi-implement | 1.0 |
| `CoreX\ImportExport\Data\CanonicalRecord` | api | 1.0 |
| `CoreX\ImportExport\Data\ConflictRule` | api | 1.0 |
| `CoreX\ImportExport\Data\ExportSession` | api | 1.0 |
| `CoreX\ImportExport\Data\Export\ExportCursor` | api | 1.0 |
| `CoreX\ImportExport\Data\Export\ExportManifest` | api | 1.0 |
| `CoreX\ImportExport\Data\Export\ExportPage` | api | 1.0 |
| `CoreX\ImportExport\Data\Export\ExportPart` | api | 1.0 |
| `CoreX\ImportExport\Data\ImportDecision` | api | 1.0 |
| `CoreX\ImportExport\Data\ImportSession` | api | 1.0 |
| `CoreX\ImportExport\Data\MappingResult` | api | 1.0 |
| `CoreX\ImportExport\Data\MappingSnapshot` | api | 1.0 |
| `CoreX\ImportExport\Data\RowBatch` | api | 1.0 |
| `CoreX\ImportExport\Data\SourceCursor` | api | 1.0 |
| `CoreX\ImportExport\Data\SourceManifest` | api | 1.0 |
| `CoreX\ImportExport\Data\SourceRow` | api | 1.0 |
| `CoreX\ImportExport\Data\StepToken` | api | 1.0 |
| `CoreX\ImportExport\Data\TargetResult` | api | 1.0 |
| `CoreX\ImportExport\Data\TransferLimits` | api | 1.0 |
| `CoreX\ImportExport\Data\TransferReceipt` | api | 1.0 |
| `CoreX\ImportExport\Data\TransferRef` | api | 1.0 |
| `CoreX\ImportExport\Data\Validation` | spi-call | 1.0 |
| `CoreX\ImportExport\Exceptions\ExportUnavailable` | api | 1.0 |
| `CoreX\ImportExport\Exceptions\ImportUnavailable` | api | 1.0 |
| `CoreX\ImportExport\Exceptions\SessionUnavailable` | api | 1.0 |
| `CoreX\ImportExport\ImportExportServiceProvider` | spi-call | 1.0 |

## axiomasoft/corex-intl

[Member manifest](../../packages/intl/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Intl\Currency\Contracts\CurrencyConverter` | spi-implement | 1.0 |
| `CoreX\Intl\Currency\Contracts\CurrencyMetadata` | spi-implement | 1.0 |
| `CoreX\Intl\Currency\Contracts\RateImporter` | spi-implement | 1.0 |
| `CoreX\Intl\Currency\Contracts\RateProvider` | spi-implement | 1.0 |
| `CoreX\Intl\Currency\Data\CurrencyInfo` | api | 1.0 |
| `CoreX\Intl\Currency\Data\RateBatch` | api | 1.0 |
| `CoreX\Intl\Currency\Data\RateImportReceipt` | api | 1.0 |
| `CoreX\Intl\Currency\Data\RateLookup` | api | 1.0 |
| `CoreX\Intl\Currency\Data\RateQuery` | api | 1.0 |
| `CoreX\Intl\Currency\Data\RateSnapshot` | api | 1.0 |
| `CoreX\Intl\Currency\Exceptions\InvalidRateException` | api | 1.0 |
| `CoreX\Intl\Currency\Exceptions\SnapshotExpiredException` | api | 1.0 |
| `CoreX\Intl\Currency\Laravel\ConversionPolicy` | spi-call | 1.0 |
| `CoreX\Intl\Currency\Laravel\ConversionReceipt` | spi-call | 1.0 |
| `CoreX\Intl\Errors\Enums\IntlErrorCode` | api | 1.0 |
| `CoreX\Intl\Formatting\Contracts\MoneyFormatter` | spi-implement | 1.0 |
| `CoreX\Intl\Formatting\Data\FormatPolicy` | api | 1.0 |
| `CoreX\Intl\Formatting\Data\FormattedMoney` | api | 1.0 |
| `CoreX\Intl\Formatting\Exceptions\UnsupportedFormattingException` | api | 1.0 |
| `CoreX\Intl\IntlModule` | spi-call | 1.0 |
| `CoreX\Intl\IntlServiceProvider` | spi-call | 1.0 |
| `CoreX\Intl\Laravel\IntlBindingsRegistrar` | spi-call | 1.0 |
| `CoreX\Intl\Laravel\IntlReferenceRegistrar` | spi-call | 1.0 |
| `CoreX\Intl\Locale\Contracts\LocaleResolver` | spi-implement | 1.0 |
| `CoreX\Intl\Locale\Contracts\LocaleValidation` | spi-call | 1.0 |
| `CoreX\Intl\Locale\Data\LocaleInputs` | api | 1.0 |
| `CoreX\Intl\Locale\Data\LocalePolicy` | api | 1.0 |
| `CoreX\Intl\Locale\Data\LocaleResolution` | api | 1.0 |
| `CoreX\Intl\Locale\Exceptions\InvalidLocaleException` | api | 1.0 |
| `CoreX\Intl\Locale\Laravel\LocalePortBindings` | spi-call | 1.0 |
| `CoreX\Intl\Money\Laravel\MoneyCast` | spi-call | 1.0 |
| `CoreX\Intl\Territory\Contracts\AddressSuggestProvider` | spi-implement | 1.0 |
| `CoreX\Intl\Territory\Contracts\DatasetImporter` | spi-implement | 1.0 |
| `CoreX\Intl\Territory\Contracts\GeographyProvider` | spi-implement | 1.0 |
| `CoreX\Intl\Territory\Data\AddressQuery` | api | 1.0 |
| `CoreX\Intl\Territory\Data\DatasetCandidate` | api | 1.0 |
| `CoreX\Intl\Territory\Data\DatasetGeneration` | api | 1.0 |
| `CoreX\Intl\Territory\Data\DatasetManifest` | api | 1.0 |
| `CoreX\Intl\Territory\Data\DatasetReceipt` | api | 1.0 |
| `CoreX\Intl\Territory\Data\GeographyQuery` | api | 1.0 |
| `CoreX\Intl\Territory\Data\ReferenceCursor` | api | 1.0 |
| `CoreX\Intl\Territory\Data\ReferenceIdentity` | api | 1.0 |
| `CoreX\Intl\Territory\Data\ReferencePage` | api | 1.0 |
| `CoreX\Intl\Territory\Data\SuggestionLimits` | api | 1.0 |
| `CoreX\Intl\Territory\Data\SuggestionResult` | api | 1.0 |
| `CoreX\Intl\Territory\Exceptions\AccessDeniedException` | api | 1.0 |
| `CoreX\Intl\Territory\Exceptions\OperationConflictException` | api | 1.0 |
| `CoreX\Intl\Territory\Exceptions\SchemaMismatchException` | api | 1.0 |
| `CoreX\Intl\Translation\Contracts\TranslationReader` | spi-implement | 1.0 |
| `CoreX\Intl\Translation\Data\LocalizedText` | api | 1.0 |
| `CoreX\Intl\Translation\Data\TranslationResult` | api | 1.0 |

## axiomasoft/corex-media

[Member manifest](../../packages/media/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Media\Contracts\ConversionDriver` | spi-implement | 1.0 |
| `CoreX\Media\Contracts\MediaLibrary` | api | 1.0 |
| `CoreX\Media\Contracts\MediaPolicy` | spi-implement | 1.0 |
| `CoreX\Media\Contracts\RelationLookup` | spi-implement | 1.0 |
| `CoreX\Media\Data\AttachmentInput` | api | 1.0 |
| `CoreX\Media\Data\AttachmentRef` | api | 1.0 |
| `CoreX\Media\Data\ConversionInput` | api | 1.0 |
| `CoreX\Media\Data\ConversionOutput` | api | 1.0 |
| `CoreX\Media\Data\ConversionProfile` | api | 1.0 |
| `CoreX\Media\Data\DeleteResult` | api | 1.0 |
| `CoreX\Media\Data\MediaAction` | api | 1.0 |
| `CoreX\Media\Data\MediaContext` | api | 1.0 |
| `CoreX\Media\Data\MediaConversionRef` | api | 1.0 |
| `CoreX\Media\Data\MediaFileRef` | api | 1.0 |
| `CoreX\Media\Data\MediaReadRef` | api | 1.0 |
| `CoreX\Media\Data\MediaSubject` | api | 1.0 |
| `CoreX\Media\Data\UploadFile` | api | 1.0 |
| `CoreX\Media\Data\UploadInput` | api | 1.0 |
| `CoreX\Media\Exceptions\MediaAccessDenied` | api | 1.0 |
| `CoreX\Media\Exceptions\MediaFileInUse` | api | 1.0 |
| `CoreX\Media\Exceptions\MediaOperationConflict` | api | 1.0 |
| `CoreX\Media\Laravel\GdThumbnailDriverFactory` | spi-call | 1.0 |
| `CoreX\Media\Laravel\MediaBindingsRegistrar` | spi-call | 1.0 |
| `CoreX\Media\Laravel\UploadedFileSource` | spi-call | 1.0 |
| `CoreX\Media\MediaModule` | spi-call | 1.0 |
| `CoreX\Media\MediaServiceProvider` | spi-call | 1.0 |

## axiomasoft/corex-notifications

[Member manifest](../../packages/notifications/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Notifications\Delivery\Contracts\DeliveryExecution` | spi-call | 1.0 |
| `CoreX\Notifications\Delivery\Contracts\NotificationChannelDriver` | spi-implement | 1.0 |
| `CoreX\Notifications\Delivery\Contracts\RecipientResolver` | spi-implement | 1.0 |
| `CoreX\Notifications\Delivery\Data\ChannelDescriptor` | api | 1.0 |
| `CoreX\Notifications\Delivery\Data\DeliveryClaim` | api | 1.0 |
| `CoreX\Notifications\Delivery\Data\DeliveryInput` | api | 1.0 |
| `CoreX\Notifications\Delivery\Data\DeliveryOutcome` | api | 1.0 |
| `CoreX\Notifications\Delivery\Data\DeliveryTarget` | api | 1.0 |
| `CoreX\Notifications\Delivery\Data\RecipientReference` | api | 1.0 |
| `CoreX\Notifications\Delivery\Laravel\DeliveryPortBindings` | spi-call | 1.0 |
| `CoreX\Notifications\Digest\Contracts\DigestBuilder` | spi-implement | 1.0 |
| `CoreX\Notifications\Digest\Data\DigestRef` | api | 1.0 |
| `CoreX\Notifications\Digest\Data\DigestWindow` | api | 1.0 |
| `CoreX\Notifications\Errors\Enums\NotificationsErrorCode` | api | 1.0 |
| `CoreX\Notifications\Inbox\Contracts\NotificationCenter` | api | 1.0 |
| `CoreX\Notifications\Inbox\Contracts\NotificationPolicy` | spi-implement | 1.0 |
| `CoreX\Notifications\Inbox\Data\CancelResult` | api | 1.0 |
| `CoreX\Notifications\Inbox\Data\EntityReference` | api | 1.0 |
| `CoreX\Notifications\Inbox\Data\InboxQuery` | api | 1.0 |
| `CoreX\Notifications\Inbox\Data\NotificationAction` | api | 1.0 |
| `CoreX\Notifications\Inbox\Data\NotificationContext` | api | 1.0 |
| `CoreX\Notifications\Inbox\Data\NotificationInput` | api | 1.0 |
| `CoreX\Notifications\Inbox\Data\NotificationRef` | api | 1.0 |
| `CoreX\Notifications\Inbox\Data\NotificationSubject` | api | 1.0 |
| `CoreX\Notifications\Inbox\Exceptions\NotificationAccessDenied` | spi-call | 1.0 |
| `CoreX\Notifications\Inbox\Exceptions\NotificationOperationConflict` | spi-call | 1.0 |
| `CoreX\Notifications\Laravel\NotificationsBindingsRegistrar` | spi-call | 1.0 |
| `CoreX\Notifications\Laravel\NotificationsDigestRegistrar` | spi-call | 1.0 |
| `CoreX\Notifications\NotificationsModule` | spi-call | 1.0 |
| `CoreX\Notifications\NotificationsServiceProvider` | spi-call | 1.0 |
| `CoreX\Notifications\Preferences\Contracts\PreferenceRepository` | spi-implement | 1.0 |
| `CoreX\Notifications\Preferences\Data\NotificationPreference` | api | 1.0 |
| `CoreX\Notifications\Preferences\Data\PreferenceInput` | api | 1.0 |

## axiomasoft/corex-operations

[Member manifest](../../packages/operations/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer` | spi-implement | 1.0 |
| `CoreX\Operations\Authorization\Contracts\OperationAuthorizer` | spi-implement | 1.0 |
| `CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer` | spi-implement | 1.0 |
| `CoreX\Operations\Authorization\Contracts\PrincipalResolver` | spi-implement | 1.0 |
| `CoreX\Operations\Authorization\Contracts\ReplayAuthorizer` | spi-implement | 1.0 |
| `CoreX\Operations\Authorization\Laravel\DeclaredFieldAuthorizer` | spi-call | 1.0 |
| `CoreX\Operations\Bulk\Contracts\BulkOperationStore` | spi-implement | 1.0 |
| `CoreX\Operations\Bulk\Contracts\BulkTransferFactory` | spi-implement | 1.0 |
| `CoreX\Operations\Bulk\Data\BulkOperation` | api | 1.0 |
| `CoreX\Operations\Bulk\Data\BulkOperationDefinition` | api | 1.0 |
| `CoreX\Operations\Bulk\Data\BulkOptions` | api | 1.0 |
| `CoreX\Operations\Bulk\Data\BulkTransferSession` | api | 1.0 |
| `CoreX\Operations\Bulk\Enums\BulkOperationStatus` | api | 1.0 |
| `CoreX\Operations\Bulk\Laravel\BulkBindingsRegistrar` | spi-call | 1.0 |
| `CoreX\Operations\Bulk\Laravel\BulkHttpRegistrar` | spi-call | 1.0 |
| `CoreX\Operations\Bulk\Laravel\BulkOptionsParser` | spi-call | 1.0 |
| `CoreX\Operations\Bulk\Laravel\BulkRouteRegistrar` | spi-call | 1.0 |
| `CoreX\Operations\Cost\Contracts\BudgetExecution` | spi-call | 1.0 |
| `CoreX\Operations\Cost\Contracts\BudgetReservation` | spi-implement | 1.0 |
| `CoreX\Operations\Cost\Contracts\OperationBudget` | spi-implement | 1.0 |
| `CoreX\Operations\Cost\Contracts\OperationCostEstimator` | spi-implement | 1.0 |
| `CoreX\Operations\Cost\Data\ActualCost` | api | 1.0 |
| `CoreX\Operations\Cost\Data\BudgetScope` | api | 1.0 |
| `CoreX\Operations\Cost\Data\CostInput` | api | 1.0 |
| `CoreX\Operations\Cost\Data\CostOptions` | api | 1.0 |
| `CoreX\Operations\Cost\Data\EstimatedCost` | api | 1.0 |
| `CoreX\Operations\Cost\Data\ThrottleStatus` | api | 1.0 |
| `CoreX\Operations\Cost\Exceptions\BudgetRateLimited` | spi-call | 1.0 |
| `CoreX\Operations\Cost\Exceptions\BudgetStoreUnavailable` | spi-call | 1.0 |
| `CoreX\Operations\Cost\Laravel\CostBindingsRegistrar` | spi-call | 1.0 |
| `CoreX\Operations\Cost\Laravel\CostOptionsParser` | spi-call | 1.0 |
| `CoreX\Operations\Definition\Contracts\OperationDefinitionValidator` | spi-call | 1.0 |
| `CoreX\Operations\Definition\Contracts\OperationRegistry` | spi-call | 1.0 |
| `CoreX\Operations\Definition\Contracts\SchemaContributor` | spi-implement | 1.0 |
| `CoreX\Operations\Definition\Data\Operation` | spi-call | 1.0 |
| `CoreX\Operations\Definition\Data\ResourceId` | api | 1.0 |
| `CoreX\Operations\Definition\Enums\OperationMode` | api | 1.0 |
| `CoreX\Operations\Definition\Enums\ResourceIdFormat` | api | 1.0 |
| `CoreX\Operations\Definition\Laravel\OperationFactory` | spi-call | 1.0 |
| `CoreX\Operations\Definition\Laravel\OperationFingerprint` | spi-call | 1.0 |
| `CoreX\Operations\Definition\Laravel\OperationPortBindings` | spi-call | 1.0 |
| `CoreX\Operations\Errors\Contracts\ErrorMapping` | spi-call | 1.0 |
| `CoreX\Operations\Errors\Contracts\ResponseSerializer` | spi-implement | 1.0 |
| `CoreX\Operations\Errors\Enums\OperationsErrorCode` | api | 1.0 |
| `CoreX\Operations\Errors\Exceptions\ApiError` | api | 1.0 |
| `CoreX\Operations\Idempotency\Contracts\IdempotencyStore` | spi-implement | 1.0 |
| `CoreX\Operations\Idempotency\Data\IdempotencyAttempt` | api | 1.0 |
| `CoreX\Operations\Idempotency\Data\IdempotencyScope` | api | 1.0 |
| `CoreX\Operations\Idempotency\Data\StoredOperationOutcome` | api | 1.0 |
| `CoreX\Operations\Idempotency\Enums\IdempotencyRequirement` | api | 1.0 |
| `CoreX\Operations\Invocation\Contracts\ContextResolver` | spi-implement | 1.0 |
| `CoreX\Operations\Invocation\Contracts\EffectiveCapabilities` | spi-call | 1.0 |
| `CoreX\Operations\Invocation\Contracts\OperationDispatcher` | spi-call | 1.0 |
| `CoreX\Operations\Invocation\Contracts\OperationInvoker` | api | 1.0 |
| `CoreX\Operations\Invocation\Contracts\RequestContextProvider` | spi-implement | 1.0 |
| `CoreX\Operations\Invocation\Data\OperationInvocation` | api | 1.0 |
| `CoreX\Operations\Invocation\Data\OperationResult` | api | 1.0 |
| `CoreX\Operations\Invocation\Enums\InputEnvelope` | api | 1.0 |
| `CoreX\Operations\Invocation\Laravel\InvocationMetadataFactory` | spi-call | 1.0 |
| `CoreX\Operations\Invocation\Laravel\ResolvedContexts` | spi-call | 1.0 |
| `CoreX\Operations\Laravel\ApiSchemaBindingsRegistrar` | spi-call | 1.0 |
| `CoreX\Operations\Laravel\ApiSchemaConnections` | spi-call | 1.0 |
| `CoreX\Operations\Laravel\HttpRegistrar` | spi-call | 1.0 |
| `CoreX\Operations\Laravel\IdempotencyRegistrar` | spi-call | 1.0 |
| `CoreX\Operations\Laravel\IdempotencyStoreFactory` | spi-call | 1.0 |
| `CoreX\Operations\Laravel\InvocationFixtures` | spi-call | 1.0 |
| `CoreX\Operations\Laravel\OperationsBindingsRegistrar` | spi-call | 1.0 |
| `CoreX\Operations\Laravel\PaginationRegistrar` | spi-call | 1.0 |
| `CoreX\Operations\Laravel\Support\ApiOptions` | spi-call | 1.0 |
| `CoreX\Operations\OpenApi\Contracts\ContractSnapshotSection` | spi-implement | 1.0 |
| `CoreX\Operations\OpenApi\Contracts\OpenApiDocuments` | spi-call | 1.0 |
| `CoreX\Operations\OperationsServiceProvider` | spi-call | 1.0 |
| `CoreX\Operations\Pagination\Contracts\CursorCodec` | spi-call | 1.0 |
| `CoreX\Operations\Pagination\Contracts\CursorKeyring` | spi-implement | 1.0 |
| `CoreX\Operations\Pagination\Contracts\OrderingRegistry` | spi-call | 1.0 |
| `CoreX\Operations\Pagination\Data\CursorPage` | api | 1.0 |
| `CoreX\Operations\Pagination\Data\CursorPayload` | api | 1.0 |
| `CoreX\Operations\Pagination\Data\CursorPosition` | api | 1.0 |
| `CoreX\Operations\Pagination\Laravel\CursorPageBuilder` | spi-call | 1.0 |
| `CoreX\Operations\Pagination\Laravel\OrderingFactory` | spi-call | 1.0 |
| `CoreX\Operations\Testing\BrokenHandler` | spi-call | 1.0 |
| `CoreX\Operations\Testing\CatalogueModuleFixture` | spi-call | 1.0 |
| `CoreX\Operations\Testing\ContextFixture` | spi-call | 1.0 |
| `CoreX\Operations\Testing\HttpDependentHandler` | spi-call | 1.0 |
| `CoreX\Operations\Testing\IdentityFixture` | spi-call | 1.0 |
| `CoreX\Operations\Testing\LoadContourModule` | spi-call | 1.0 |
| `CoreX\Operations\Testing\ModuleFixture` | spi-call | 1.0 |
| `CoreX\Operations\Testing\MutatingHandler` | spi-call | 1.0 |
| `CoreX\Operations\Testing\NoteHandler` | spi-call | 1.0 |
| `CoreX\Operations\Testing\OutputMismatchHandler` | spi-call | 1.0 |
| `CoreX\Operations\Testing\ProviderKeyHandler` | spi-call | 1.0 |
| `CoreX\Operations\Testing\StubOperationHandler` | spi-call | 1.0 |
| `CoreX\Operations\Testing\StubToolHandler` | spi-call | 1.0 |
| `CoreX\Operations\Validation\Data\JsonObject` | api | 1.0 |
| `CoreX\Operations\Validation\Data\JsonValue` | api | 1.0 |
| `CoreX\Operations\Validation\Data\SchemaHandle` | api | 1.0 |
| `CoreX\Operations\Validation\Data\SchemaValidation` | api | 1.0 |
| `CoreX\Operations\Validation\Laravel\JsonCanonical` | spi-call | 1.0 |
| `CoreX\Operations\Validation\Laravel\JsonObjectFactory` | spi-call | 1.0 |
| `CoreX\Operations\Validation\Laravel\JsonValueFactory` | spi-call | 1.0 |
| `CoreX\Operations\Validation\Laravel\SchemaBudget` | spi-call | 1.0 |
| `CoreX\Operations\Validation\Laravel\SchemaProfile` | spi-call | 1.0 |
| `CoreX\Operations\Versioning\Contracts\ApiProfiles` | spi-call | 1.0 |
| `CoreX\Operations\Versioning\Data\ApiProfile` | api | 1.0 |
| `CoreX\Operations\Versioning\Data\ApiVersion` | api | 1.0 |
| `CoreX\Operations\Versioning\Data\ApiVersionStatus` | api | 1.0 |
| `CoreX\Operations\Versioning\Data\CapabilityDescriptor` | api | 1.0 |
| `CoreX\Operations\Versioning\Data\DeprecationDescriptor` | api | 1.0 |
| `CoreX\Operations\Versioning\Data\EffectiveCapability` | api | 1.0 |

## axiomasoft/corex-panels

[Member manifest](../../packages/panels/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Panels\Laravel\PanelContexts` | spi-call | 1.0 |
| `CoreX\Panels\PanelsServiceProvider` | spi-call | 1.0 |

## axiomasoft/corex-recurrence

[Member manifest](../../packages/recurrence/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Recurrence\Contracts\RecurrenceExpander` | spi-implement | 1.0 |
| `CoreX\Recurrence\Data\DateStart` | api | 1.0 |
| `CoreX\Recurrence\Data\DateWindow` | api | 1.0 |
| `CoreX\Recurrence\Data\ExpansionCursor` | api | 1.0 |
| `CoreX\Recurrence\Data\ExpansionLimits` | api | 1.0 |
| `CoreX\Recurrence\Data\InstantWindow` | api | 1.0 |
| `CoreX\Recurrence\Data\Occurrence` | api | 1.0 |
| `CoreX\Recurrence\Data\OccurrencePage` | api | 1.0 |
| `CoreX\Recurrence\Data\RecurrenceOverride` | api | 1.0 |
| `CoreX\Recurrence\Data\RecurrenceSet` | api | 1.0 |
| `CoreX\Recurrence\Data\RecurrenceWindow` | api | 1.0 |
| `CoreX\Recurrence\Errors\Enums\RecurrenceErrorCode` | api | 1.0 |
| `CoreX\Recurrence\Exceptions\StaleCursorException` | api | 1.0 |
| `CoreX\Recurrence\Exceptions\UnsupportedEnvironmentException` | api | 1.0 |
| `CoreX\Recurrence\Laravel\RecurrenceFactory` | spi-call | 1.0 |
| `CoreX\Recurrence\RecurrenceServiceProvider` | spi-call | 1.0 |

## axiomasoft/corex-recurring-tasks

[Member manifest](../../packages/recurring-tasks/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\RecurringTasks\Contracts\TaskRecurrenceCommand` | api | 1.0 |
| `CoreX\RecurringTasks\Data\MaterializationReceipt` | api | 1.0 |
| `CoreX\RecurringTasks\Data\MaterializationWindow` | api | 1.0 |
| `CoreX\RecurringTasks\Data\SeriesReference` | api | 1.0 |
| `CoreX\RecurringTasks\Errors\Enums\RecurringTasksErrorCode` | api | 1.0 |
| `CoreX\RecurringTasks\Exceptions\MaterializationConflict` | api | 1.0 |
| `CoreX\RecurringTasks\Exceptions\StaleMaterialization` | api | 1.0 |
| `CoreX\RecurringTasks\Exceptions\UnsupportedMaterialization` | api | 1.0 |
| `CoreX\RecurringTasks\Laravel\CompletionBindings` | spi-call | 1.0 |
| `CoreX\RecurringTasks\RecurringTasksServiceProvider` | spi-call | 1.0 |

## axiomasoft/corex-scheduler

[Member manifest](../../packages/scheduler/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Scheduler\Laravel\WakeupBindingsRegistrar` | spi-call | 1.0 |
| `CoreX\Scheduler\SchedulerModule` | spi-call | 1.0 |
| `CoreX\Scheduler\SchedulerServiceProvider` | spi-call | 1.0 |
| `CoreX\Scheduler\Testing\WakeupFixtures` | spi-call | 1.0 |

## axiomasoft/corex-search

[Member manifest](../../packages/search/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Search\Contracts\IndexDriver` | spi-implement | 1.0 |
| `CoreX\Search\Contracts\SearchDocuments` | spi-implement | 1.0 |
| `CoreX\Search\Contracts\SearchDriverRegistry` | spi-call | 1.0 |
| `CoreX\Search\Contracts\SearchHydrator` | spi-implement | 1.0 |
| `CoreX\Search\Contracts\SearchProvider` | spi-implement | 1.0 |
| `CoreX\Search\Contracts\SearchSchema` | spi-implement | 1.0 |
| `CoreX\Search\Data\Candidate` | api | 1.0 |
| `CoreX\Search\Data\CandidatePage` | api | 1.0 |
| `CoreX\Search\Data\CurrentSearchRecord` | api | 1.0 |
| `CoreX\Search\Data\DocumentBatch` | api | 1.0 |
| `CoreX\Search\Data\DocumentCursor` | api | 1.0 |
| `CoreX\Search\Data\IndexBatch` | api | 1.0 |
| `CoreX\Search\Data\IndexClaim` | api | 1.0 |
| `CoreX\Search\Data\IndexOutcome` | api | 1.0 |
| `CoreX\Search\Data\IndexSubmission` | api | 1.0 |
| `CoreX\Search\Data\IndexSwap` | api | 1.0 |
| `CoreX\Search\Data\SearchCapabilities` | api | 1.0 |
| `CoreX\Search\Data\SearchFilter` | api | 1.0 |
| `CoreX\Search\Data\SearchPage` | api | 1.0 |
| `CoreX\Search\Data\SearchQuery` | api | 1.0 |
| `CoreX\Search\Data\SearchSnapshot` | api | 1.0 |
| `CoreX\Search\Errors\Enums\SearchErrorCode` | api | 1.0 |
| `CoreX\Search\Exceptions\InvalidSearchConfiguration` | api | 1.0 |
| `CoreX\Search\Laravel\SearchPortBindings` | spi-call | 1.0 |
| `CoreX\Search\SearchServiceProvider` | spi-call | 1.0 |
| `CoreX\Search\Testing\SearchFixtures` | spi-call | 1.0 |

## axiomasoft/corex-search-intl

[Member manifest](../../packages/search-intl/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\SearchIntl\Contracts\SavedViewLabelReader` | spi-implement | 1.0 |
| `CoreX\SearchIntl\Data\LocalizedFieldBinding` | api | 1.0 |
| `CoreX\SearchIntl\SearchIntlServiceProvider` | spi-call | 1.0 |

## axiomasoft/corex-search-meilisearch

[Member manifest](../../packages/search-meilisearch/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\SearchMeilisearch\Errors\Enums\SearchMeilisearchErrorCode` | api | 1.0 |
| `CoreX\SearchMeilisearch\Exceptions\InvalidMeilisearchConfiguration` | api | 1.0 |
| `CoreX\SearchMeilisearch\Laravel\MeilisearchProviderFactory` | spi-call | 1.0 |
| `CoreX\SearchMeilisearch\SearchMeilisearchServiceProvider` | spi-call | 1.0 |

## axiomasoft/corex-tasks

[Member manifest](../../packages/tasks/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Tasks\Access\Contracts\TaskAccess` | spi-implement | 1.0 |
| `CoreX\Tasks\Access\Contracts\TaskModuleContext` | spi-call | 1.0 |
| `CoreX\Tasks\Access\Contracts\TaskMutationAccess` | spi-implement | 1.0 |
| `CoreX\Tasks\Access\Contracts\TaskPlanningAccess` | spi-implement | 1.0 |
| `CoreX\Tasks\Access\Exceptions\TasksModuleDisabled` | api | 1.0 |
| `CoreX\Tasks\Errors\Enums\TasksErrorCode` | api | 1.0 |
| `CoreX\Tasks\Laravel\TasksRegistrar` | spi-call | 1.0 |
| `CoreX\Tasks\Planning\Contracts\TaskOperationReceipts` | spi-implement | 1.0 |
| `CoreX\Tasks\Planning\Contracts\TaskPlanningCommand` | spi-implement | 1.0 |
| `CoreX\Tasks\Planning\Data\PlanningOperation` | api | 1.0 |
| `CoreX\Tasks\Planning\Data\PlanningSubject` | api | 1.0 |
| `CoreX\Tasks\Planning\Data\PlanningToken` | api | 1.0 |
| `CoreX\Tasks\Planning\Data\TaskOperationKind` | api | 1.0 |
| `CoreX\Tasks\Planning\Data\TaskOperationReceipt` | api | 1.0 |
| `CoreX\Tasks\Planning\Exceptions\InvalidPlanningDraft` | api | 1.0 |
| `CoreX\Tasks\Planning\Exceptions\PlanningConflict` | api | 1.0 |
| `CoreX\Tasks\Planning\Laravel\PlanningCommand` | spi-call | 1.0 |
| `CoreX\Tasks\Project\Contracts\ProjectRead` | api | 1.0 |
| `CoreX\Tasks\Project\Data\ProjectDraft` | api | 1.0 |
| `CoreX\Tasks\Project\Data\ProjectReference` | api | 1.0 |
| `CoreX\Tasks\Project\Data\ProjectView` | api | 1.0 |
| `CoreX\Tasks\Project\Enums\ProjectStatus` | api | 1.0 |
| `CoreX\Tasks\Project\Laravel\ProjectRelations` | spi-call | 1.0 |
| `CoreX\Tasks\Sprint\Contracts\SprintRead` | api | 1.0 |
| `CoreX\Tasks\Sprint\Data\SprintDraft` | api | 1.0 |
| `CoreX\Tasks\Sprint\Data\SprintReference` | api | 1.0 |
| `CoreX\Tasks\Sprint\Data\SprintView` | api | 1.0 |
| `CoreX\Tasks\Sprint\Enums\SprintStatus` | api | 1.0 |
| `CoreX\Tasks\Sprint\Laravel\SprintRelations` | spi-call | 1.0 |
| `CoreX\Tasks\Task\Contracts\CompleteTaskCommand` | api | 1.0 |
| `CoreX\Tasks\Task\Contracts\CreateTaskCommand` | api | 1.0 |
| `CoreX\Tasks\Task\Contracts\MoveTaskToStageCommand` | api | 1.0 |
| `CoreX\Tasks\Task\Contracts\TaskCompletionCapture` | spi-implement | 1.0 |
| `CoreX\Tasks\Task\Contracts\UpdateTaskCommand` | api | 1.0 |
| `CoreX\Tasks\Task\Data\ActorData` | api | 1.0 |
| `CoreX\Tasks\Task\Data\CompleteTaskData` | api | 1.0 |
| `CoreX\Tasks\Task\Data\EntityReference` | api | 1.0 |
| `CoreX\Tasks\Task\Data\MoveTaskToStageData` | api | 1.0 |
| `CoreX\Tasks\Task\Data\StageView` | api | 1.0 |
| `CoreX\Tasks\Task\Data\TaskCompletionCandidate` | api | 1.0 |
| `CoreX\Tasks\Task\Data\TaskDraft` | api | 1.0 |
| `CoreX\Tasks\Task\Data\TaskReference` | api | 1.0 |
| `CoreX\Tasks\Task\Data\TaskView` | api | 1.0 |
| `CoreX\Tasks\Task\Data\UpdateTaskData` | api | 1.0 |
| `CoreX\Tasks\Task\Enums\TaskPriority` | api | 1.0 |
| `CoreX\Tasks\Task\Enums\TaskStatus` | api | 1.0 |
| `CoreX\Tasks\Task\Events\TaskCompleted` | spi-call | 1.0 |
| `CoreX\Tasks\Task\Events\TaskCreated` | spi-call | 1.0 |
| `CoreX\Tasks\Task\Events\TaskMovedToStage` | spi-call | 1.0 |
| `CoreX\Tasks\Task\Events\TaskUpdated` | spi-call | 1.0 |
| `CoreX\Tasks\Task\Laravel\StageRelations` | spi-call | 1.0 |
| `CoreX\Tasks\Task\Laravel\TaskPortBindings` | spi-call | 1.0 |
| `CoreX\Tasks\Task\Laravel\TaskRelations` | spi-call | 1.0 |
| `CoreX\Tasks\TasksModule` | spi-call | 1.0 |
| `CoreX\Tasks\TasksServiceProvider` | spi-call | 1.0 |
| `CoreX\Tasks\Time\Data\TimeLogDraft` | api | 1.0 |
| `CoreX\Tasks\Time\Data\TimeLogReference` | api | 1.0 |
| `CoreX\Tasks\Time\Enums\TimeLogSource` | api | 1.0 |
| `CoreX\Tasks\Time\Laravel\TimeLogRelations` | spi-call | 1.0 |

## axiomasoft/corex-tenancy

[Member manifest](../../packages/tenancy/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Tenancy\Account\Contracts\AccountRead` | api | 1.0 |
| `CoreX\Tenancy\Account\Events\AccountPurged` | spi-call | 1.0 |
| `CoreX\Tenancy\Account\Events\AccountSuspended` | spi-call | 1.0 |
| `CoreX\Tenancy\Account\Events\ExportReady` | spi-call | 1.0 |
| `CoreX\Tenancy\Account\Events\GraceStarted` | spi-call | 1.0 |
| `CoreX\Tenancy\Account\Events\TrialStarted` | spi-call | 1.0 |
| `CoreX\Tenancy\Account\Laravel\AccountRelations` | spi-call | 1.0 |
| `CoreX\Tenancy\Account\Laravel\AccountTemplateSnapshot` | spi-call | 1.0 |
| `CoreX\Tenancy\Account\Laravel\MigrationWaveOrchestrator` | spi-call | 1.0 |
| `CoreX\Tenancy\Authentication\Exceptions\OidcAuthenticationException` | spi-call | 1.0 |
| `CoreX\Tenancy\Contracts\CentralApiClient` | spi-implement | 1.0 |
| `CoreX\Tenancy\Contracts\DomainChallengeStore` | spi-implement | 1.0 |
| `CoreX\Tenancy\Contracts\DomainOwnershipProofVerifier` | spi-implement | 1.0 |
| `CoreX\Tenancy\Contracts\DomainTrustAuthorizer` | spi-implement | 1.0 |
| `CoreX\Tenancy\Contracts\DomainTrustPolicy` | spi-implement | 1.0 |
| `CoreX\Tenancy\Contracts\PrincipalWorkspaceResolver` | spi-implement | 1.0 |
| `CoreX\Tenancy\Contracts\ProvisioningClaim` | spi-implement | 1.0 |
| `CoreX\Tenancy\Contracts\TemplateIntegrityVerifier` | spi-implement | 1.0 |
| `CoreX\Tenancy\DomainTrust\Data\ChallengeConsumption` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Data\DomainChallenge` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Data\DomainChallengeBinding` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Data\DomainChallengeDraft` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Data\DomainTrustActor` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Data\DomainTrustTransition` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Data\IssuedDomainChallenge` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Data\NormalizedHost` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Data\ObservedDomainProof` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Enums\DomainTrustChangeReason` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Enums\DomainTrustOperation` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Events\DomainTrustChanged` | spi-call | 1.0 |
| `CoreX\Tenancy\DomainTrust\Exceptions\DomainTrustDenied` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Exceptions\DomainTrustDisabled` | api | 1.0 |
| `CoreX\Tenancy\DomainTrust\Laravel\DomainRelations` | spi-call | 1.0 |
| `CoreX\Tenancy\Errors\Enums\TenancyErrorCode` | api | 1.0 |
| `CoreX\Tenancy\Identity\Contracts\IdentitySyncer` | spi-implement | 1.0 |
| `CoreX\Tenancy\Identity\Contracts\MembershipProjection` | spi-call | 1.0 |
| `CoreX\Tenancy\Identity\Events\UserSynced` | spi-call | 1.0 |
| `CoreX\Tenancy\Identity\Laravel\UserRelations` | spi-call | 1.0 |
| `CoreX\Tenancy\Impersonation\Contracts\ImpersonationAuditSink` | spi-implement | 1.0 |
| `CoreX\Tenancy\Impersonation\Contracts\ImpersonationGrantStore` | spi-implement | 1.0 |
| `CoreX\Tenancy\Impersonation\Data\ClaimedGrant` | api | 1.0 |
| `CoreX\Tenancy\Impersonation\Data\GrantClaim` | api | 1.0 |
| `CoreX\Tenancy\Impersonation\Data\ImpersonationAuditEvent` | api | 1.0 |
| `CoreX\Tenancy\Impersonation\Enums\ImpersonationProfile` | api | 1.0 |
| `CoreX\Tenancy\Impersonation\Events\ImpersonationEnded` | spi-call | 1.0 |
| `CoreX\Tenancy\Impersonation\Events\ImpersonationStarted` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\AuthenticationRegistrar` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Commands\IdentitiesReconcileCommand` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Commands\TenantsMigrateCommand` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\ConsoleRegistrar` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\DomainTrustRegistrar` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\EventsRegistrar` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\HttpRegistrar` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Http\Middleware\AccountStatusGate` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Http\Middleware\AllowImpersonatedWrites` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Http\Middleware\DenyImpersonatedWrites` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Http\Middleware\EnforceSessionLifetime` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Http\Middleware\ImpersonationSessionGuard` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Http\Middleware\RequireActiveMembership` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Http\Middleware\ResolveWorkspace` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Http\Middleware\ScopeSessionToTenant` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\IdentityRegistrar` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\ProvisioningRegistrar` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\RuntimeRegistrar` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Scopes\WorkspaceScope` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Support\AccountLifecyclePolicy` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Support\HostResolverCacheInvalidator` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Support\ImpersonationPolicy` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Support\ResolvesTenancyOptions` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Support\StatusGatePolicy` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Support\TenancyConfigSection` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\Support\TenancyOptions` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\TenancyPortBindings` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\WakeupRegistrar` | spi-call | 1.0 |
| `CoreX\Tenancy\Laravel\WorkspaceRegistrar` | spi-call | 1.0 |
| `CoreX\Tenancy\Membership\Contracts\AccountClickAuthorizer` | spi-implement | 1.0 |
| `CoreX\Tenancy\Membership\Contracts\AccountMenuCache` | spi-implement | 1.0 |
| `CoreX\Tenancy\Membership\Contracts\AccountsReadTransport` | spi-implement | 1.0 |
| `CoreX\Tenancy\Membership\Data\AccountListEntry` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\AccountsQuery` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\ActorRef` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\AvailableAccounts` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\ClockReading` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\MembershipQuery` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\MembershipSnapshot` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\PairKey` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\PairState` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\PairWitness` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\RevokeMembershipReceipt` | api | 1.0 |
| `CoreX\Tenancy\Membership\Data\RevokeMembershipRequest` | api | 1.0 |
| `CoreX\Tenancy\Membership\Enums\ActorType` | api | 1.0 |
| `CoreX\Tenancy\Membership\Enums\MembershipEventKind` | api | 1.0 |
| `CoreX\Tenancy\Membership\Enums\MembershipReason` | api | 1.0 |
| `CoreX\Tenancy\Membership\Enums\MembershipState` | api | 1.0 |
| `CoreX\Tenancy\Membership\Events\MembershipGranted` | spi-call | 1.0 |
| `CoreX\Tenancy\Membership\Events\MembershipRevoked` | spi-call | 1.0 |
| `CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation` | api | 1.0 |
| `CoreX\Tenancy\Membership\Exceptions\CentralUnauthorized` | api | 1.0 |
| `CoreX\Tenancy\Membership\Exceptions\CentralUnavailable` | api | 1.0 |
| `CoreX\Tenancy\Membership\Exceptions\CredentialIssuanceDenied` | api | 1.0 |
| `CoreX\Tenancy\Membership\Exceptions\InboxConflict` | api | 1.0 |
| `CoreX\Tenancy\Membership\Exceptions\PairStoreViolation` | api | 1.0 |
| `CoreX\Tenancy\Membership\Exceptions\ProjectionDenied` | api | 1.0 |
| `CoreX\Tenancy\Membership\Exceptions\RevokeUncertain` | api | 1.0 |
| `CoreX\Tenancy\Membership\Laravel\MembershipSnapshotFactory` | spi-call | 1.0 |
| `CoreX\Tenancy\Membership\Laravel\RevokeMembershipReceiptFactory` | spi-call | 1.0 |
| `CoreX\Tenancy\Provisioning\Contracts\AccountResources` | spi-implement | 1.0 |
| `CoreX\Tenancy\Provisioning\Contracts\TemplateIntegrityChecks` | spi-call | 1.0 |
| `CoreX\Tenancy\Provisioning\Data\AccountArchive` | api | 1.0 |
| `CoreX\Tenancy\Provisioning\Data\ClaimRequest` | api | 1.0 |
| `CoreX\Tenancy\Provisioning\Data\ClaimResult` | api | 1.0 |
| `CoreX\Tenancy\Provisioning\Data\QualifiedTemplate` | api | 1.0 |
| `CoreX\Tenancy\Provisioning\Data\TemplateAccountView` | api | 1.0 |
| `CoreX\Tenancy\Provisioning\Exceptions\ClaimConflict` | api | 1.0 |
| `CoreX\Tenancy\Provisioning\Exceptions\ClaimUnavailable` | api | 1.0 |
| `CoreX\Tenancy\Provisioning\Exceptions\TemplateIntegrityException` | api | 1.0 |
| `CoreX\Tenancy\Provisioning\Laravel\MigrationBatchRelations` | spi-call | 1.0 |
| `CoreX\Tenancy\Runtime\Contracts\AccountIsolationMapper` | spi-implement | 1.0 |
| `CoreX\Tenancy\Runtime\Contracts\TenantBindingAuthority` | spi-implement | 2.0.0 |
| `CoreX\Tenancy\Runtime\Events\TenantContextEnded` | api | 1.0 |
| `CoreX\Tenancy\Runtime\Events\TenantContextInitialized` | api | 1.0 |
| `CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable` | api | 1.0 |
| `CoreX\Tenancy\Runtime\Exceptions\TenantCouldNotBeIdentifiedByHostException` | api | 1.0 |
| `CoreX\Tenancy\TenancyServiceProvider` | spi-call | 1.0 |
| `CoreX\Tenancy\Workspace\Contracts\WorkspaceResolver` | spi-implement | 1.0 |
| `CoreX\Tenancy\Workspace\Exceptions\InvitationNotAcceptableException` | api | 1.0 |
| `CoreX\Tenancy\Workspace\Exceptions\NoDefaultWorkspaceException` | api | 1.0 |

## axiomasoft/corex-tools

[Member manifest](../../packages/tools/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Tools\Contracts\ToolCatalogue` | api | 1.0 |
| `CoreX\Tools\Contracts\ToolExecutionGuard` | spi-implement | 1.0 |
| `CoreX\Tools\Contracts\ToolInvoker` | api | 1.0 |
| `CoreX\Tools\Contracts\ToolPolicy` | spi-implement | 1.0 |
| `CoreX\Tools\Contracts\ToolRequestContext` | spi-implement | 1.0 |
| `CoreX\Tools\Contracts\ToolSchemaValidator` | spi-implement | 1.0 |
| `CoreX\Tools\Data\ToolCall` | api | 1.0 |
| `CoreX\Tools\Data\ToolCatalogueView` | api | 1.0 |
| `CoreX\Tools\Data\ToolDescriptor` | api | 1.0 |
| `CoreX\Tools\Data\ToolOutcome` | api | 1.0 |
| `CoreX\Tools\Data\ToolResult` | api | 1.0 |
| `CoreX\Tools\Errors\Enums\ToolsErrorCode` | api | 1.0 |
| `CoreX\Tools\ToolsServiceProvider` | spi-call | 1.0 |

## axiomasoft/corex-webhooks

[Member manifest](../../packages/webhooks/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Webhooks\Contracts\WebhookDeliveryStore` | spi-implement | 1.0 |
| `CoreX\Webhooks\Contracts\WebhookEgressPolicy` | spi-implement | 1.0 |
| `CoreX\Webhooks\Contracts\WebhookHttpTransport` | spi-implement | 1.0 |
| `CoreX\Webhooks\Contracts\WebhookPayloadProjector` | spi-implement | 1.0 |
| `CoreX\Webhooks\Contracts\WebhookSigner` | spi-implement | 1.0 |
| `CoreX\Webhooks\Contracts\WebhookSubscriptionStore` | spi-implement | 1.0 |
| `CoreX\Webhooks\Data\ValidatedWebhookRequest` | api | 1.0 |
| `CoreX\Webhooks\Data\WebhookDelivery` | api | 1.0 |
| `CoreX\Webhooks\Data\WebhookDestination` | api | 1.0 |
| `CoreX\Webhooks\Data\WebhookHttpResult` | api | 1.0 |
| `CoreX\Webhooks\Data\WebhookOptions` | api | 1.0 |
| `CoreX\Webhooks\Data\WebhookSignedHeaders` | api | 1.0 |
| `CoreX\Webhooks\Data\WebhookSubscription` | api | 1.0 |
| `CoreX\Webhooks\Data\WebhookTopic` | api | 1.0 |
| `CoreX\Webhooks\Enums\WebhookFailureReason` | api | 1.0 |
| `CoreX\Webhooks\Errors\Enums\WebhooksErrorCode` | api | 1.0 |
| `CoreX\Webhooks\Laravel\WebhookBindingsRegistrar` | spi-call | 1.0 |
| `CoreX\Webhooks\Laravel\WebhookController` | spi-call | 1.0 |
| `CoreX\Webhooks\Laravel\WebhookHttpRegistrar` | spi-call | 1.0 |
| `CoreX\Webhooks\Laravel\WebhookOptionsParser` | spi-call | 1.0 |
| `CoreX\Webhooks\WebhooksServiceProvider` | spi-call | 1.0 |

## axiomasoft/corex-workflow

[Member manifest](../../packages/workflow/resources/contracts/php-api.json)

| Symbol | Layer | Since |
|:--|:--|:--|
| `CoreX\Workflow\Contracts\EngineAdapter` | spi-implement | 1.0 |
| `CoreX\Workflow\Contracts\WorkflowBinding` | spi-implement | 1.0 |
| `CoreX\Workflow\Data\CaptureResult` | api | 1.0 |
| `CoreX\Workflow\Data\SignalRequest` | api | 1.0 |
| `CoreX\Workflow\Data\SignalResult` | api | 1.0 |
| `CoreX\Workflow\Data\StartResult` | api | 1.0 |
| `CoreX\Workflow\Data\TransitionRequest` | api | 1.0 |
| `CoreX\Workflow\Data\TransitionResult` | api | 1.0 |
| `CoreX\Workflow\Data\TriggerRequest` | api | 1.0 |
| `CoreX\Workflow\Laravel\WorkflowBindingFactory` | spi-call | 1.0 |
| `CoreX\Workflow\WorkflowModule` | spi-call | 1.0 |
| `CoreX\Workflow\WorkflowServiceProvider` | spi-call | 1.0 |
