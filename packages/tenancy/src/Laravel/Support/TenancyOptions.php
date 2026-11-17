<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Support;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\SecretRef;
use CoreX\Config\Laravel\ConfigurationValidator;
use CoreX\Tenancy\Impersonation\Enums\ImpersonationProfile;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class TenancyOptions
{
    /**
     * @param  list<string>  $oidcAllowedAlgs
     */
    public function __construct(
        public string $centralConnection,
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
        public string $membershipGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $centralConnection = self::nonEmpty(violations: $violations, path: 'central_connection', value: $config['central_connection'] ?? 'pgsql');

        if (($config['bootstrappers'] ?? []) !== []) {
            $violations->add(path: 'corex-tenancy.bootstrappers', message: 'is no longer supported; compose facilities through axiomasoft/tenant-laravel');
        }

        $storage = is_array($config['storage'] ?? null) ? $config['storage'] : [];
        $storageDurableDriver = self::nonEmpty(
            violations: $violations,
            path: 'storage.durable_driver',
            value: $storage['durable_driver'] ?? 'local',
        );
        $storageDurableRoot = self::nonEmpty(
            violations: $violations,
            path: 'storage.durable_root',
            value: $storage['durable_root'] ?? 'storage/app/corex/tenancy-durable',
        );
        $storageEphemeralRoot = self::nonEmpty(
            violations: $violations,
            path: 'storage.ephemeral_root',
            value: $storage['ephemeral_root'] ?? 'storage/app/corex/tenancy-ephemeral',
        );

        $hostIdentification = is_array($config['host_identification'] ?? null)
            ? $config['host_identification']
            : [];
        $hostIdentificationEnabled = self::bool(
            violations: $violations,
            path: 'host_identification.enabled',
            value: $hostIdentification['enabled'] ?? false,
        );
        $hostCache = self::bool(
            violations: $violations,
            path: 'host_identification.cache',
            value: $hostIdentification['cache'] ?? true,
        );
        $hostCacheTtl = self::nonNegativeInt(
            violations: $violations,
            path: 'host_identification.cache_ttl',
            value: $hostIdentification['cache_ttl'] ?? 300,
        );
        $hostCacheStore = self::nullableNonEmptyString(
            $hostIdentification['cache_store'] ?? null,
        );

        $domainTrust = is_array($config['domain_trust'] ?? null) ? $config['domain_trust'] : [];
        $domainTrustEnabled = self::bool(violations: $violations, path: 'domain_trust.enabled', value: $domainTrust['enabled'] ?? false);
        $domainTrustChallengeTtlSeconds = self::positiveInt(
            violations: $violations,
            path: 'domain_trust.challenge_ttl_seconds',
            value: $domainTrust['challenge_ttl_seconds'] ?? 300,
        );

        $statusGate = self::statusGate(violations: $violations, config: $config);

        $oidc = is_array($config['oidc'] ?? null) ? $config['oidc'] : [];
        $oidcEnabled = self::bool(violations: $violations, path: 'oidc.enabled', value: $oidc['enabled'] ?? false);
        $oidcIssuer = self::nullableNonEmptyString($oidc['issuer'] ?? null);
        $oidcClientId = self::nullableNonEmptyString($oidc['client_id'] ?? null);
        $oidcClientSecret = self::optionalSecret(violations: $violations, path: 'oidc.client_secret', value: $oidc['client_secret'] ?? null);
        $oidcRedirectUri = self::nullableNonEmptyString($oidc['redirect_uri'] ?? null);
        $oidcAllowedAlgs = self::stringList($oidc['allowed_algs'] ?? ['RS256']);
        $oidcLeewaySeconds = self::nonNegativeInt(violations: $violations, path: 'oidc.leeway_seconds', value: $oidc['leeway_seconds'] ?? 30);
        $oidcGuard = self::nonEmpty(violations: $violations, path: 'oidc.guard', value: $oidc['guard'] ?? 'web');

        $discovery = is_array($oidc['discovery'] ?? null) ? $oidc['discovery'] : [];
        $oidcDiscoveryCacheStore = self::nullableNonEmptyString($discovery['cache_store'] ?? null);
        $oidcDiscoveryCacheTtl = self::positiveInt(
            violations: $violations,
            path: 'oidc.discovery.cache_ttl',
            value: $discovery['cache_ttl'] ?? 3600,
        );

        $jwks = is_array($oidc['jwks'] ?? null) ? $oidc['jwks'] : [];
        $oidcJwksCacheStore = self::nullableNonEmptyString($jwks['cache_store'] ?? null);
        $oidcJwksCacheTtl = self::positiveInt(violations: $violations, path: 'oidc.jwks.cache_ttl', value: $jwks['cache_ttl'] ?? 3600);
        $oidcJwksRefetchCooldownSeconds = self::positiveInt(
            violations: $violations,
            path: 'oidc.jwks.refetch_cooldown_seconds',
            value: $jwks['refetch_cooldown_seconds'] ?? 60,
        );

        $impersonationConfig = is_array($oidc['impersonation'] ?? null) ? $oidc['impersonation'] : [];
        $profileConfig = is_array($config['impersonation'] ?? null) ? $config['impersonation'] : [];
        $configuredProfile = $profileConfig['profile'] ?? 'standard';
        $profile = is_string($configuredProfile) ? ImpersonationProfile::tryFrom($configuredProfile) : null;

        if ($profile === null) {
            $violations->add(path: 'corex-tenancy.impersonation.profile', message: 'must be dev, standard, or regulated');
            $profile = ImpersonationProfile::Standard;
        }

        $impersonation = new ImpersonationPolicy(
            enabled: self::bool(violations: $violations, path: 'oidc.impersonation.enabled', value: $impersonationConfig['enabled'] ?? false),
            profile: $profile,
        );

        $oidcMaxSessionLifetimeMinutes = self::positiveInt(
            violations: $violations,
            path: 'oidc.max_session_lifetime_minutes',
            value: $oidc['max_session_lifetime_minutes'] ?? 720,
        );

        if ($oidcEnabled) {
            if ($oidcIssuer === null) {
                $violations->add(path: 'oidc.issuer', message: 'must be non-empty when oidc.enabled is true');
            }

            if ($oidcClientId === null) {
                $violations->add(path: 'oidc.client_id', message: 'must be non-empty when oidc.enabled is true');
            }

            if ($oidcRedirectUri === null) {
                $violations->add(path: 'oidc.redirect_uri', message: 'must be non-empty when oidc.enabled is true');
            }

            if ($oidcClientSecret === null || ! $oidcClientSecret->isPresent()) {
                $violations->add(path: 'oidc.client_secret', message: 'must be present when oidc.enabled is true');
            }

            if ($oidcAllowedAlgs === []) {
                $violations->add(path: 'oidc.allowed_algs', message: 'must not be empty when oidc.enabled is true');
            }
        }

        $workspace = is_array($config['workspace'] ?? null) ? $config['workspace'] : [];
        $workspacePathSegment = self::nullableNonEmptyString($workspace['path_segment'] ?? 'w');

        $lifecycle = new AccountLifecyclePolicy(
            trialDays: self::nonNegativeInt(violations: $violations, path: 'trial_days', value: $config['trial_days'] ?? 14),
            graceDays: self::nonNegativeInt(violations: $violations, path: 'grace_days', value: $config['grace_days'] ?? 30),
            platformDomain: self::nonEmpty(violations: $violations, path: 'platform_domain', value: $config['platform_domain'] ?? 'example.test'),
        );

        $wakeup = is_array($config['wakeup'] ?? null) ? $config['wakeup'] : [];
        $wakeupDriver = is_string($wakeup['driver'] ?? null) ? $wakeup['driver'] : 'local_sweep';

        if (! in_array($wakeupDriver, ['local_sweep', 'root_registry'], true)) {
            $violations->add(path: 'wakeup.driver', message: 'must be local_sweep or root_registry');
            $wakeupDriver = 'local_sweep';
        }

        $wakeupRootConnection = is_string($wakeup['root_connection'] ?? null)
            ? $wakeup['root_connection']
            : '';

        if ($wakeupDriver === 'root_registry' && $wakeupRootConnection === '') {
            $violations->add(path: 'wakeup.root_connection', message: 'must be non-empty when wakeup.driver is root_registry');
        }

        $wakeupLeaseSeconds = self::positiveInt(violations: $violations, path: 'wakeup.lease_seconds', value: $wakeup['lease_seconds'] ?? 60);

        $membershipGuardConfig = is_array($config['membership_guard'] ?? null)
            ? $config['membership_guard']
            : [];
        $membershipGuard = self::nonEmpty(
            violations: $violations,
            path: 'membership_guard.guard',
            value: $membershipGuardConfig['guard'] ?? 'web',
        );

        $violations->failIfAny();

        return new self(
            centralConnection: $centralConnection,
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
            strtoupper(...),
            $methods,
        )));

        if ($normalized === []) {
            $violations->add(path: 'status_gate.read_only_methods', message: 'must not be empty');
            $normalized = ['GET'];
        }

        return new StatusGatePolicy(
            paymentRoute: $paymentRoute,
            exportRoute: $exportRoute,
            readOnlyMethods: $normalized,
        );
    }

    private static function optionalSecret(ConfigurationValidator $violations, string $path, mixed $value): ?SecretRef
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return new SecretRef($value);
        }

        $violations->add(path: $path, message: 'must be a string or null');

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

    private static function bool(ConfigurationValidator $violations, string $path, mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a boolean');

        return false;
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a non-empty string');

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

        $violations->add(path: $path, message: 'must be a non-negative integer');

        return 0;
    }

    private static function positiveInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a positive integer');

        return 1;
    }
}
