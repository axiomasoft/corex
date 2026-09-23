<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Support;

use CoreX\Support\SecretRef;
use CoreX\Support\ViolationList;
use CoreX\Tenancy\Jobs\CreateDatabaseFromTemplate;
use Stancl\Tenancy\Contracts\UniqueIdentifierGenerator;

final readonly class TenancyOptions
{
    /**
     * @param  class-string  $tenantModel
     * @param  class-string  $idGenerator
     * @param  class-string  $tenantDatabaseManager
     * @param  list<class-string>  $bootstrapperOrder
     * @param  list<class-string>  $provisioningPipeline
     * @param  list<string>  $oidcAllowedAlgs
     * @param  array<string, string>  $templateIntegrityTrustedKeys
     * @param  array<string, array<string, mixed>>  $templateIntegrityManifests
     */
    public function __construct(
        public string $centralConnection,
        public string $tenantModel,
        public string $idGenerator,
        public string $tenantDatabaseManager,
        public array $bootstrapperOrder,
        public array $provisioningPipeline,
        public string $cachePrefix,
        public string $storageDurableDriver,
        public string $storageDurableRoot,
        public string $storageEphemeralRoot,
        public bool $hostIdentificationEnabled,
        public bool $hostIdentificationCache,
        public int $hostIdentificationCacheTtl,
        public ?string $hostIdentificationCacheStore,
        public bool $domainTrustEnabled,
        public int $domainTrustChallengeTtlSeconds,
        public StatusGatePolicy $statusGate,
        public bool $oidcEnabled,
        public ?string $oidcIssuer,
        public ?string $oidcClientId,
        public ?SecretRef $oidcClientSecret,
        public ?string $oidcRedirectUri,
        public array $oidcAllowedAlgs,
        public int $oidcLeewaySeconds,
        public string $oidcGuard,
        public ?string $oidcDiscoveryCacheStore,
        public int $oidcDiscoveryCacheTtl,
        public ?string $oidcJwksCacheStore,
        public int $oidcJwksCacheTtl,
        public int $oidcJwksRefetchCooldownSeconds,
        public ImpersonationPolicy $impersonation,
        public int $oidcMaxSessionLifetimeMinutes,
        public ?string $workspacePathSegment,
        public AccountLifecyclePolicy $lifecycle,
        public string $wakeupDriver,
        public string $wakeupRootConnection,
        public int $wakeupLeaseSeconds,
        public array $templateIntegrityTrustedKeys,
        public array $templateIntegrityManifests,
        public string $membershipGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $tenancy
     * @param  array<string, mixed>  $corexTenancy
     */
    public static function fromArray(array $tenancy, array $corexTenancy): self
    {
        $violations = new ViolationList;

        $centralConnection = self::nonEmpty($violations, 'central_connection', $tenancy['central_connection'] ?? 'pgsql');
        $tenantModel = self::classString($violations, 'tenant_model', $tenancy['tenant_model'] ?? null);
        $idGenerator = self::classString(
            $violations,
            'id_generator',
            $tenancy['id_generator'] ?? null,
            UniqueIdentifierGenerator::class,
        );
        $tenantDatabaseManager = self::classString(
            $violations,
            'tenant_database_manager',
            $tenancy['tenant_database_manager'] ?? null,
        );

        $bootstrapperOrder = self::classList(
            $violations,
            'bootstrapper_order',
            $tenancy['bootstrapper_order'] ?? [],
            required: true,
        );

        $provisioning = is_array($tenancy['provisioning'] ?? null) ? $tenancy['provisioning'] : [];
        $provisioningPipeline = self::classList(
            $violations,
            'provisioning.pipeline',
            $provisioning['pipeline'] ?? [CreateDatabaseFromTemplate::class],
            required: true,
        );

        $cachePrefix = self::nonEmpty($violations, 'cache_prefix', $tenancy['cache_prefix'] ?? 'tenant_%tenant%_');

        $storage = is_array($tenancy['storage'] ?? null) ? $tenancy['storage'] : [];
        $storageDurableDriver = self::nonEmpty(
            $violations,
            'storage.durable_driver',
            $storage['durable_driver'] ?? 'local',
        );
        $storageDurableRoot = self::nonEmpty(
            $violations,
            'storage.durable_root',
            $storage['durable_root'] ?? 'storage/app/corex/tenancy-durable',
        );
        $storageEphemeralRoot = self::nonEmpty(
            $violations,
            'storage.ephemeral_root',
            $storage['ephemeral_root'] ?? 'storage/app/corex/tenancy-ephemeral',
        );

        $hostIdentification = is_array($tenancy['host_identification'] ?? null)
            ? $tenancy['host_identification']
            : [];
        $hostIdentificationEnabled = self::bool(
            $violations,
            'host_identification.enabled',
            $hostIdentification['enabled'] ?? false,
        );
        $hostIdentificationCache = self::bool(
            $violations,
            'host_identification.cache',
            $hostIdentification['cache'] ?? true,
        );
        $hostIdentificationCacheTtl = self::nonNegativeInt(
            $violations,
            'host_identification.cache_ttl',
            $hostIdentification['cache_ttl'] ?? 300,
        );
        $hostIdentificationCacheStore = self::nullableNonEmptyString(
            $hostIdentification['cache_store'] ?? null,
        );

        $domainTrust = is_array($tenancy['domain_trust'] ?? null) ? $tenancy['domain_trust'] : [];
        $domainTrustEnabled = self::bool($violations, 'domain_trust.enabled', $domainTrust['enabled'] ?? false);
        $domainTrustChallengeTtlSeconds = self::positiveInt(
            $violations,
            'domain_trust.challenge_ttl_seconds',
            $domainTrust['challenge_ttl_seconds'] ?? 300,
        );

        $statusGate = self::statusGate($violations, $tenancy);

        $oidc = is_array($tenancy['oidc'] ?? null) ? $tenancy['oidc'] : [];
        $oidcEnabled = self::bool($violations, 'oidc.enabled', $oidc['enabled'] ?? false);
        $oidcIssuer = self::nullableNonEmptyString($oidc['issuer'] ?? null);
        $oidcClientId = self::nullableNonEmptyString($oidc['client_id'] ?? null);
        $oidcClientSecret = self::optionalSecret($violations, 'oidc.client_secret', $oidc['client_secret'] ?? null);
        $oidcRedirectUri = self::nullableNonEmptyString($oidc['redirect_uri'] ?? null);
        $oidcAllowedAlgs = self::stringList($oidc['allowed_algs'] ?? ['RS256']);
        $oidcLeewaySeconds = self::nonNegativeInt($violations, 'oidc.leeway_seconds', $oidc['leeway_seconds'] ?? 30);
        $oidcGuard = self::nonEmpty($violations, 'oidc.guard', $oidc['guard'] ?? 'web');

        $discovery = is_array($oidc['discovery'] ?? null) ? $oidc['discovery'] : [];
        $oidcDiscoveryCacheStore = self::nullableNonEmptyString($discovery['cache_store'] ?? null);
        $oidcDiscoveryCacheTtl = self::positiveInt(
            $violations,
            'oidc.discovery.cache_ttl',
            $discovery['cache_ttl'] ?? 3600,
        );

        $jwks = is_array($oidc['jwks'] ?? null) ? $oidc['jwks'] : [];
        $oidcJwksCacheStore = self::nullableNonEmptyString($jwks['cache_store'] ?? null);
        $oidcJwksCacheTtl = self::positiveInt($violations, 'oidc.jwks.cache_ttl', $jwks['cache_ttl'] ?? 3600);
        $oidcJwksRefetchCooldownSeconds = self::positiveInt(
            $violations,
            'oidc.jwks.refetch_cooldown_seconds',
            $jwks['refetch_cooldown_seconds'] ?? 60,
        );

        $impersonationConfig = is_array($oidc['impersonation'] ?? null) ? $oidc['impersonation'] : [];
        $impersonation = new ImpersonationPolicy(
            enabled: self::bool($violations, 'oidc.impersonation.enabled', $impersonationConfig['enabled'] ?? false),
            requireApprovedBy: self::bool(
                $violations,
                'oidc.impersonation.require_approved_by',
                $impersonationConfig['require_approved_by'] ?? false,
            ),
        );

        $oidcMaxSessionLifetimeMinutes = self::positiveInt(
            $violations,
            'oidc.max_session_lifetime_minutes',
            $oidc['max_session_lifetime_minutes'] ?? 720,
        );

        if ($oidcEnabled) {
            if ($oidcIssuer === null) {
                $violations->add('oidc.issuer', 'must be non-empty when oidc.enabled is true');
            }

            if ($oidcClientId === null) {
                $violations->add('oidc.client_id', 'must be non-empty when oidc.enabled is true');
            }

            if ($oidcRedirectUri === null) {
                $violations->add('oidc.redirect_uri', 'must be non-empty when oidc.enabled is true');
            }

            if ($oidcClientSecret === null || ! $oidcClientSecret->isPresent()) {
                $violations->add('oidc.client_secret', 'must be present when oidc.enabled is true');
            }

            if ($oidcAllowedAlgs === []) {
                $violations->add('oidc.allowed_algs', 'must not be empty when oidc.enabled is true');
            }
        }

        $workspace = is_array($tenancy['workspace'] ?? null) ? $tenancy['workspace'] : [];
        $workspacePathSegment = self::nullableNonEmptyString($workspace['path_segment'] ?? 'w');

        $lifecycle = new AccountLifecyclePolicy(
            trialDays: self::nonNegativeInt($violations, 'trial_days', $tenancy['trial_days'] ?? 14),
            graceDays: self::nonNegativeInt($violations, 'grace_days', $tenancy['grace_days'] ?? 30),
            platformDomain: self::nonEmpty($violations, 'platform_domain', $tenancy['platform_domain'] ?? 'example.test'),
        );

        $wakeup = is_array($corexTenancy['wakeup'] ?? null) ? $corexTenancy['wakeup'] : [];
        $wakeupDriver = is_string($wakeup['driver'] ?? null) ? $wakeup['driver'] : 'local_sweep';
        if (! in_array($wakeupDriver, ['local_sweep', 'root_registry'], true)) {
            $violations->add('wakeup.driver', 'must be local_sweep or root_registry');
            $wakeupDriver = 'local_sweep';
        }

        $wakeupRootConnection = is_string($wakeup['root_connection'] ?? null)
            ? $wakeup['root_connection']
            : '';
        if ($wakeupDriver === 'root_registry' && $wakeupRootConnection === '') {
            $violations->add('wakeup.root_connection', 'must be non-empty when wakeup.driver is root_registry');
        }

        $wakeupLeaseSeconds = self::positiveInt($violations, 'wakeup.lease_seconds', $wakeup['lease_seconds'] ?? 60);

        $templateIntegrity = is_array($tenancy['template_integrity'] ?? null)
            ? $tenancy['template_integrity']
            : [];
        $templateIntegrityTrustedKeys = self::stringMap($templateIntegrity['trusted_keys'] ?? []);
        $templateIntegrityManifests = self::arrayMap($templateIntegrity['manifests'] ?? []);

        $membershipGuardConfig = is_array($tenancy['membership_guard'] ?? null)
            ? $tenancy['membership_guard']
            : [];
        $membershipGuard = self::nonEmpty(
            $violations,
            'membership_guard.guard',
            $membershipGuardConfig['guard'] ?? 'web',
        );

        $violations->failIfAny();

        return new self(
            centralConnection: $centralConnection,
            tenantModel: $tenantModel,
            idGenerator: $idGenerator,
            tenantDatabaseManager: $tenantDatabaseManager,
            bootstrapperOrder: $bootstrapperOrder,
            provisioningPipeline: $provisioningPipeline,
            cachePrefix: $cachePrefix,
            storageDurableDriver: $storageDurableDriver,
            storageDurableRoot: $storageDurableRoot,
            storageEphemeralRoot: $storageEphemeralRoot,
            hostIdentificationEnabled: $hostIdentificationEnabled,
            hostIdentificationCache: $hostIdentificationCache,
            hostIdentificationCacheTtl: $hostIdentificationCacheTtl,
            hostIdentificationCacheStore: $hostIdentificationCacheStore,
            domainTrustEnabled: $domainTrustEnabled,
            domainTrustChallengeTtlSeconds: $domainTrustChallengeTtlSeconds,
            statusGate: $statusGate,
            oidcEnabled: $oidcEnabled,
            oidcIssuer: $oidcIssuer,
            oidcClientId: $oidcClientId,
            oidcClientSecret: $oidcClientSecret,
            oidcRedirectUri: $oidcRedirectUri,
            oidcAllowedAlgs: $oidcAllowedAlgs,
            oidcLeewaySeconds: $oidcLeewaySeconds,
            oidcGuard: $oidcGuard,
            oidcDiscoveryCacheStore: $oidcDiscoveryCacheStore,
            oidcDiscoveryCacheTtl: $oidcDiscoveryCacheTtl,
            oidcJwksCacheStore: $oidcJwksCacheStore,
            oidcJwksCacheTtl: $oidcJwksCacheTtl,
            oidcJwksRefetchCooldownSeconds: $oidcJwksRefetchCooldownSeconds,
            impersonation: $impersonation,
            oidcMaxSessionLifetimeMinutes: $oidcMaxSessionLifetimeMinutes,
            workspacePathSegment: $workspacePathSegment,
            lifecycle: $lifecycle,
            wakeupDriver: $wakeupDriver,
            wakeupRootConnection: $wakeupRootConnection,
            wakeupLeaseSeconds: $wakeupLeaseSeconds,
            templateIntegrityTrustedKeys: $templateIntegrityTrustedKeys,
            templateIntegrityManifests: $templateIntegrityManifests,
            membershipGuard: $membershipGuard,
        );
    }

    /** @param array<string, mixed> $tenancy */
    private static function statusGate(ViolationList $violations, array $tenancy): StatusGatePolicy
    {
        $statusGate = is_array($tenancy['status_gate'] ?? null) ? $tenancy['status_gate'] : [];
        $paymentRoute = self::nullableNonEmptyString($statusGate['payment_route'] ?? null);
        $exportRoute = self::nullableNonEmptyString($statusGate['export_route'] ?? null);
        $methods = self::stringList($statusGate['read_only_methods'] ?? ['GET', 'HEAD', 'OPTIONS']);
        $normalized = array_values(array_unique(array_map(
            static fn (string $method): string => strtoupper($method),
            $methods,
        )));

        if ($normalized === []) {
            $violations->add('status_gate.read_only_methods', 'must not be empty');
            $normalized = ['GET'];
        }

        return new StatusGatePolicy(
            paymentRoute: $paymentRoute,
            exportRoute: $exportRoute,
            readOnlyMethods: $normalized,
        );
    }

    private static function optionalSecret(ViolationList $violations, string $path, mixed $value): ?SecretRef
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return new SecretRef($value);
        }

        $violations->add($path, 'must be a string or null');

        return null;
    }

    /** @return class-string */
    private static function classString(
        ViolationList $violations,
        string $path,
        mixed $value,
        ?string $contract = null,
    ): string {
        if (is_string($value) && $value !== '' && class_exists($value)) {
            if ($contract !== null && ! is_a($value, $contract, true)) {
                $violations->add($path, 'must be a class implementing '.$contract);

                return $contract;
            }

            return $value;
        }

        $message = $contract === null
            ? 'must be an existing class-string'
            : 'must be a class implementing '.$contract;
        $violations->add($path, $message);

        return $contract ?? 'invalid';
    }

    /**
     * @return list<class-string>
     */
    private static function classList(
        ViolationList $violations,
        string $path,
        mixed $value,
        bool $required,
    ): array {
        if (! is_array($value)) {
            $violations->add($path, 'must be a list of class names');

            return [];
        }

        if ($required && $value === []) {
            $violations->add($path, 'must not be empty');
        }

        $resolved = [];

        foreach ($value as $index => $class) {
            if (! is_string($class) || $class === '' || ! class_exists($class)) {
                $violations->add($path.'.'.$index, 'must be an existing class-string');

                continue;
            }

            $resolved[] = $class;
        }

        return $resolved;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $resolved = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    /** @return array<string, string> */
    private static function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $resolved = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $key !== '' && is_string($item) && $item !== '') {
                $resolved[$key] = $item;
            }
        }

        return $resolved;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function arrayMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $resolved = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $key !== '' && is_array($item)) {
                $resolved[$key] = $item;
            }
        }

        return $resolved;
    }

    private static function bool(ViolationList $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add($path, 'must be a boolean');

        return false;
    }

    private static function nonEmpty(ViolationList $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }

    private static function nullableNonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function nonNegativeInt(ViolationList $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        $violations->add($path, 'must be a non-negative integer');

        return 0;
    }

    private static function positiveInt(ViolationList $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add($path, 'must be a positive integer');

        return 1;
    }
}
