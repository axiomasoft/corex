<?php

declare(strict_types=1);

namespace CoreX\Config\Data;

use CoreX\Attributes\Api;
use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Contracts\AuditRetention;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Database\Enums\IdStrategy;
use CoreX\Departments\Contracts\DepartmentRepository;
use CoreX\Features\Contracts\FeatureFlags;
use CoreX\Messaging\Enums\PubSubDriver;
use CoreX\Settings\Contracts\SettingDefaultsProvider;
use CoreX\Settings\Contracts\SettingsRepository;

#[Api(since: '1.0')]
final readonly class CoreOptions
{
    /**
     * @param  class-string<TenantContextResolver>  $tenantContextResolver
     * @param  class-string<TenancyManager>  $tenancyManager
     * @param  class-string<ImpersonationService>  $impersonationService
     * @param  class-string<SettingsRepository>  $settingsRepository
     * @param  class-string<AuditLogger>  $auditLogger
     * @param  class-string<AuditRetention>  $auditRetention
     * @param  class-string<SettingDefaultsProvider>  $settingDefaultsProvider
     * @param  class-string<FeatureFlags>  $featureFlags
     * @param  class-string<DepartmentRepository>  $departmentRepository
     * @param  class-string<DataScopeAuthorizer>  $dataScopeAuthorizer
     * @param  list<string>  $acknowledgedDangerousModes  exact config paths of dangerous modes accepted in production
     */
    public function __construct(
        public IdStrategy $idStrategy,
        public string $settingsTable,
        public string $auditLogsTable,
        public string $featureFlagsTable,
        public string $featureOverridesTable,
        public string $departmentsTable,
        public string $departmentUserTable,
        public string $pubsubMessagesTable,
        public string $pubsubCursorsTable,
        public string $savedViewsTable,
        public string $savedViewScopesTable,
        public string $outboxMessagesTable,
        public string $inboxMessagesTable,
        public string $tenantContextResolver,
        public string $tenancyManager,
        public string $impersonationService,
        public string $settingsRepository,
        public string $auditLogger,
        public string $auditRetention,
        public string $settingDefaultsProvider,
        public string $featureFlags,
        public string $departmentRepository,
        public string $dataScopeAuthorizer,
        public string $storageDurableRoot,
        public string $storageEphemeralRoot,
        public ?string $viewsConnection,
        public bool $auditEnabled,
        public string $settingsCacheStore,
        public ?int $settingsCacheTtl,
        public string $settingsCachePrefix,
        public PubSubDriver $pubsubDriver,
        public int $pubsubPollIntervalMicroseconds,
        public bool $pubsubOctaneWorkerOptIn,
        public string $pubsubRetention,
        public int $messagingLeaseSeconds,
        public int $messagingBatchSize,
        public int $messagingMaxAttempts,
        public int $messagingBackoffCapSeconds,
        public string $messagingOutboxRetention,
        public string $messagingDeadRetention,
        public ?string $messagingInboxRetention,
        public array $acknowledgedDangerousModes = [],
    ) {}
}
