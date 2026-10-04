<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Support;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\SecretRef;
use CoreX\Config\Laravel\ConfigurationValidator;
use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\CachePrefixBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\ConnectionBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\SessionConnectionBootstrapper;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class TenancyOptions
{
    /**
     * @param  list<class-string<TenantBootstrapper>>  $bootstrappers
     * @param  list<string>  $cacheStores
     * @param  list<string>  $oidcAllowedAlgs
     * @param  array<string, string>  $templateIntegrityTrustedKeys
     * @param  array<string, array<string, mixed>>  $templateIntegrityManifests
     */
    public function __construct(
        public string $centralConnection,
        public array $bootstrappers,
        public string $cachePrefix,
        public array $cacheStores,
        public string $storageDurableDriver,
        public string $storageDurableRoot,
        public string $storageEphemeralRoot,
        public bool $hostIdentificationEnabled,
        public bool $hostCache,
        public int $hostCacheTtl,
        public ?string $hostCacheStore,
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
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $centralConnection = self::nonEmpty($violations, 'central_connection', $config['central_connection'] ?? 'pgsql');
        $bootstrappers = self::bootstrappers($violations, $config['bootstrappers'] ?? null);

        $cachePrefix = self::nonEmpty($violations, 'cache_prefix', $config['cache_prefix'] ?? 'tenant_%tenant%_');

        if (! str_contains($cachePrefix, '%tenant%')) {
            $violations->add('corex-tenancy.cache_prefix', 'must contain %tenant%');
        }

        $cacheStores = self::cacheStores($violations, $config['cache_stores'] ?? []);

        $storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
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

        $hostIdentification = is_array($config['host_identification'] ?? null)
            ? $config['host_identification']
            : [];
        $hostIdentificationEnabled = self::bool(
            $violations,
            'host_identification.enabled',
            $hostIdentification['enabled'] ?? false,
        );
        $hostCache = self::bool(
            $violations,
            'host_identification.cache',
            $hostIdentification['cache'] ?? true,
        );
        $hostCacheTtl = self::nonNegativeInt(
            $violations,
            'host_identification.cache_ttl',
            $hostIdentification['cache_ttl'] ?? 300,
        );
        $hostCacheStore = self::nullableNonEmptyString(
            $hostIdentification['cache_store'] ?? null,
        );

        // Host lookups run before any account context and must stay central: the host store is
        // never one of the per-account (prefixed) stores.
        if ($hostCacheStore !== null && in_array($hostCacheStore, $cacheStores, true)) {
            $violations->add('corex-tenancy.host_identification.cache_store', 'must not be one of cache_stores');
        }

        $domainTrust = is_array($config['domain_trust'] ?? null) ? $config['domain_trust'] : [];
        $domainTrustEnabled = self::bool($violations, 'domain_trust.enabled', $domainTrust['enabled'] ?? false);
        $domainTrustChallengeTtlSeconds = self::positiveInt(
            $violations,
            'domain_trust.challenge_ttl_seconds',
            $domainTrust['challenge_ttl_seconds'] ?? 300,
        );

        $statusGate = self::statusGate($violations, $config);

        $oidc = is_array($config['oidc'] ?? null) ? $config['oidc'] : [];
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

        $workspace = is_array($config['workspace'] ?? null) ? $config['workspace'] : [];
        $workspacePathSegment = self::nullableNonEmptyString($workspace['path_segment'] ?? 'w');

        $lifecycle = new AccountLifecyclePolicy(
            trialDays: self::nonNegativeInt($violations, 'trial_days', $config['trial_days'] ?? 14),
            graceDays: self::nonNegativeInt($violations, 'grace_days', $config['grace_days'] ?? 30),
            platformDomain: self::nonEmpty($violations, 'platform_domain', $config['platform_domain'] ?? 'example.test'),
        );

        $wakeup = is_array($config['wakeup'] ?? null) ? $config['wakeup'] : [];
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

        $templateIntegrity = is_array($config['template_integrity'] ?? null)
            ? $config['template_integrity']
            : [];
        $templateIntegrityTrustedKeys = self::stringMap($templateIntegrity['trusted_keys'] ?? []);
        $templateIntegrityManifests = self::arrayMap($templateIntegrity['manifests'] ?? []);

        $membershipGuardConfig = is_array($config['membership_guard'] ?? null)
            ? $config['membership_guard']
            : [];
        $membershipGuard = self::nonEmpty(
            $violations,
            'membership_guard.guard',
            $membershipGuardConfig['guard'] ?? 'web',
        );

        $violations->failIfAny();

        return new self(
            centralConnection: $centralConnection,
            bootstrappers: $bootstrappers,
            cachePrefix: $cachePrefix,
            cacheStores: $cacheStores,
            storageDurableDriver: $storageDurableDriver,
            storageDurableRoot: $storageDurableRoot,
            storageEphemeralRoot: $storageEphemeralRoot,
            hostIdentificationEnabled: $hostIdentificationEnabled,
            hostCache: $hostCache,
            hostCacheTtl: $hostCacheTtl,
            hostCacheStore: $hostCacheStore,
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

    /** @param array<string, mixed> $config */
    private static function statusGate(ConfigurationValidator $violations, array $config): StatusGatePolicy
    {
        $statusGate = is_array($config['status_gate'] ?? null) ? $config['status_gate'] : [];
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

    /**
     * Closed order: the stock bootstrappers keep their relative order with the connection one
     * first and exactly once; host-supplied bootstrappers may follow, never precede.
     *
     * @return list<class-string<TenantBootstrapper>>
     */
    private static function bootstrappers(ConfigurationValidator $violations, mixed $value): array
    {
        $value ??= [
            ConnectionBootstrapper::class,
            CachePrefixBootstrapper::class,
            SessionConnectionBootstrapper::class,
        ];

        if (! is_array($value) || $value === []) {
            $violations->add('corex-tenancy.bootstrappers', 'must be a non-empty list of class names');

            return [];
        }

        $stock = [ConnectionBootstrapper::class, CachePrefixBootstrapper::class, SessionConnectionBootstrapper::class];
        $resolved = [];
        $lastStock = -1;
        $hostSeen = false;

        foreach (array_values($value) as $index => $class) {
            $path = 'corex-tenancy.bootstrappers.'.$index;

            if (! is_string($class) || ! class_exists($class) || ! is_a($class, TenantBootstrapper::class, true)) {
                $violations->add($path, 'must be an existing class implementing '.TenantBootstrapper::class);

                continue;
            }

            if (in_array($class, $resolved, true)) {
                $violations->add($path, 'must not be listed twice');

                continue;
            }

            $position = array_search($class, $stock, true);

            if ($position === false) {
                $hostSeen = true;
            } elseif ($hostSeen || $position < $lastStock) {
                $violations->add($path, 'stock bootstrappers must keep their order and precede host bootstrappers');
            } else {
                $lastStock = $position;
            }

            $resolved[] = $class;
        }

        if (($resolved[0] ?? null) !== ConnectionBootstrapper::class) {
            $violations->add('corex-tenancy.bootstrappers.0', ConnectionBootstrapper::class.' must come first');
        }

        return $resolved;
    }

    /** @return list<string> */
    private static function cacheStores(ConfigurationValidator $violations, mixed $value): array
    {
        if (! is_array($value)) {
            $violations->add('corex-tenancy.cache_stores', 'must be a list of cache store names');

            return [];
        }

        $stores = [];

        foreach (array_values($value) as $index => $store) {
            if (! is_string($store) || $store === '') {
                $violations->add('corex-tenancy.cache_stores.'.$index, 'must be a non-empty store name');

                continue;
            }

            if (in_array($store, $stores, true)) {
                $violations->add('corex-tenancy.cache_stores.'.$index, 'must be unique');

                continue;
            }

            $stores[] = $store;
        }

        return $stores;
    }

    private static function optionalSecret(ConfigurationValidator $violations, string $path, mixed $value): ?SecretRef
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

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add($path, 'must be a boolean');

        return false;
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
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

    private static function nonNegativeInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value >= 0) {
            return $value;
        }

        $violations->add($path, 'must be a non-negative integer');

        return 0;
    }

    private static function positiveInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add($path, 'must be a positive integer');

        return 1;
    }
}
