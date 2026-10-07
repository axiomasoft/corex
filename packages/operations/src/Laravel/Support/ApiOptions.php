<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel\Support;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\SecretRef;
use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Config\Laravel\ConfigurationValidator;
use CoreX\Operations\Versioning\Data\ApiVersion;
use CoreX\Operations\Versioning\Data\ApiVersionStatus;
use CoreX\Operations\Versioning\Data\CapabilityDescriptor;
use InvalidArgumentException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ApiOptions
{
    /**
     * @param  list<ApiVersion>  $versions
     * @param  list<string>  $routeMiddleware
     * @param  array<string, SecretRef>  $cursorKeyring
     * @param  array<string, array{status: string, limits: array<string, int|string>}>  $capabilities
     */
    public function __construct(
        public bool $requirePhysicalTenancy,
        public bool $routesEnabled,
        public string $routePrefix,
        public array $routeMiddleware,
        public array $versions,
        public string $defaultMajor,
        public string $product,
        public string $build,
        public int $idempotencyRetentionSeconds,
        public int $idempotencyLeaseSeconds,
        public int $cursorTtlSeconds,
        public string $cursorKeyId,
        public array $cursorKeyring,
        public string $discovery,
        public array $capabilities,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $routes = self::array($config['routes'] ?? []);
        $idempotency = self::array($config['idempotency'] ?? []);
        $cursor = self::array($config['cursor'] ?? []);
        $versions = self::versions($violations, $config['versions'] ?? null);
        $defaultMajor = self::nonEmpty($violations, 'default_major', $config['default_major'] ?? 'v1');
        $declared = array_map(static fn (ApiVersion $version): string => $version->major, $versions);
        $default = null;

        foreach ($versions as $version) {
            if ($version->major === $defaultMajor) {
                $default = $version;
            }
        }

        if (! in_array($defaultMajor, $declared, true) || ! $default instanceof ApiVersion || $default->status !== ApiVersionStatus::Stable) {
            $violations->add('default_major', 'must name a declared stable major');
        }

        $discovery = self::nonEmpty($violations, 'discovery', $config['discovery'] ?? 'public');

        if (! in_array($discovery, ['public', 'authenticated'], true)) {
            $violations->add('discovery', 'must be public or authenticated');
        }

        $keyId = self::nonEmpty($violations, 'cursor.current_key_id', $cursor['current_key_id'] ?? 'primary');
        $keyring = self::keyring($violations, $cursor['keyring'] ?? [$keyId => '']);

        if (! array_key_exists($keyId, $keyring)) {
            $violations->add('cursor.keyring', 'must contain current_key_id');
        }

        try {
            $capabilities = self::capabilities($config['capabilities'] ?? []);
        } catch (InvalidArgumentException $exception) {
            $violations->add('capabilities', $exception->getMessage());
            $capabilities = [];
        }

        $requirePhysicalTenancy = self::bool($violations, 'require_physical_tenancy', $config['require_physical_tenancy'] ?? true);
        $routesEnabled = self::bool($violations, 'routes.enabled', $routes['enabled'] ?? true);
        $routePrefix = self::nonEmpty($violations, 'routes.prefix', $routes['prefix'] ?? 'api');
        $routeMiddleware = self::middleware($violations, $routes['middleware'] ?? []);
        $product = self::nonEmpty($violations, 'product', $config['product'] ?? 'corex');
        $build = self::nonEmpty($violations, 'build', $config['build'] ?? 'dev');
        $retention = self::positiveInt($violations, 'idempotency.retention_seconds', $idempotency['retention_seconds'] ?? 86400);
        $lease = self::positiveInt($violations, 'idempotency.lease_seconds', $idempotency['lease_seconds'] ?? 30);
        $cursorTtl = self::positiveInt($violations, 'cursor.ttl_seconds', $cursor['ttl_seconds'] ?? 3600);

        $violations->failIfAny();

        return new self(
            requirePhysicalTenancy: $requirePhysicalTenancy,
            routesEnabled: $routesEnabled,
            routePrefix: $routePrefix,
            routeMiddleware: $routeMiddleware,
            versions: $versions,
            defaultMajor: $defaultMajor,
            product: $product,
            build: $build,
            idempotencyRetentionSeconds: $retention,
            idempotencyLeaseSeconds: $lease,
            cursorTtlSeconds: $cursorTtl,
            cursorKeyId: $keyId,
            cursorKeyring: $keyring,
            discovery: $discovery,
            capabilities: $capabilities,
        );
    }

    /** @return array<string, mixed> */
    public function publicProjection(): array
    {
        return [
            'build' => $this->build,
            'cursor' => [
                'current_key_id' => $this->cursorKeyId,
                'ttl_seconds' => $this->cursorTtlSeconds,
            ],
            'default_major' => $this->defaultMajor,
            'discovery' => $this->discovery,
            'idempotency' => [
                'lease_seconds' => $this->idempotencyLeaseSeconds,
                'retention_seconds' => $this->idempotencyRetentionSeconds,
            ],
            'product' => $this->product,
            'routes' => [
                'enabled' => $this->routesEnabled,
                'middleware' => $this->routeMiddleware,
                'prefix' => $this->routePrefix,
            ],
            'versions' => array_map(static fn (ApiVersion $version): array => $version->toArray(), $this->versions),
        ];
    }

    public function defaultVersion(): ApiVersion
    {
        foreach ($this->versions as $version) {
            if ($version->major === $this->defaultMajor) {
                return $version;
            }
        }

        throw new InvalidConfiguration(['default_major: must name a declared stable major']);
    }

    /** @return array<array-key, mixed> */
    private static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<ApiVersion>
     */
    private static function versions(ConfigurationValidator $violations, mixed $value): array
    {
        if ($value === null) {
            return [new ApiVersion('v1', ApiVersionStatus::Stable, null)];
        }

        if (! is_array($value) || $value === []) {
            $violations->add('versions', 'must be a non-empty list');

            return [];
        }

        $versions = [];
        $seen = [];

        foreach ($value as $index => $row) {
            if (! is_array($row)) {
                $violations->add('versions.'.$index, 'must be an object');

                continue;
            }

            $major = is_string($row['major'] ?? null) ? $row['major'] : '';
            $status = ApiVersionStatus::tryFrom(is_string($row['status'] ?? null) ? $row['status'] : '');
            $supported = $row['supported_through'] ?? null;

            if ($status === null) {
                $violations->add('versions.'.$index.'.status', 'must be stable, deprecated, or retired');

                continue;
            }

            if (! is_string($supported) && $supported !== null) {
                $violations->add('versions.'.$index.'.supported_through', 'must be a release line or null');

                continue;
            }

            try {
                $version = new ApiVersion($major, $status, $supported);
            } catch (InvalidArgumentException $exception) {
                $violations->add('versions.'.$index, $exception->getMessage());

                continue;
            }

            if (isset($seen[$version->major])) {
                $violations->add('versions.'.$index.'.major', 'must be unique');

                continue;
            }

            $seen[$version->major] = true;
            $versions[] = $version;
        }

        return $versions;
    }

    /**
     * @return array<string, array{status: string, limits: array<string, int|string>}>
     */
    private static function capabilities(mixed $value): array
    {
        if ($value === []) {
            return [];
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException('must be a map');
        }

        $capabilities = [];

        foreach ($value as $id => $row) {
            if (! is_string($id) || ! is_array($row)) {
                throw new InvalidArgumentException('must be a map of descriptors');
            }

            $extra = array_diff(array_keys($row), ['status', 'limits']);

            if ($extra !== [] || array_intersect($extra, CapabilityDescriptor::FORBIDDEN_KEYS) !== [] || array_intersect(array_keys($row), CapabilityDescriptor::FORBIDDEN_KEYS) !== []) {
                throw new InvalidArgumentException('public map rejects infrastructure keys');
            }

            $status = is_string($row['status'] ?? null) ? $row['status'] : 'available';
            $limits = is_array($row['limits'] ?? null) ? $row['limits'] : [];
            $publicLimits = [];
            foreach ($limits as $key => $limitValue) {
                if (! is_string($key)) {
                    throw new InvalidArgumentException('capability: public map rejects infrastructure keys');
                }

                if (! is_int($limitValue) && ! is_string($limitValue)) {
                    throw new InvalidArgumentException('capability: limits must be bounded scalars');
                }
                $publicLimits[$key] = $limitValue;
            }
            CapabilityDescriptor::assertPublicLimits($publicLimits);
            $limits = $publicLimits;

            if (preg_match('/^[a-z][a-z0-9_.-]*\.v[1-9][0-9]*$/', $id) !== 1 || ! in_array($status, ['available', 'deprecated'], true)) {
                throw new InvalidArgumentException('id and status must be public');
            }

            /** @var array<string, int|string> $limits */
            $capabilities[$id] = ['status' => $status, 'limits' => $limits];
        }

        ksort($capabilities);

        return $capabilities;
    }

    /** @return array<string, SecretRef> */
    private static function keyring(ConfigurationValidator $violations, mixed $value): array
    {
        if (! is_array($value)) {
            $violations->add('cursor.keyring', 'must be a map of SecretRef values');

            return [];
        }

        $keyring = [];

        foreach ($value as $id => $secret) {
            if (! is_string($id) || $id === '') {
                $violations->add('cursor.keyring', 'keys must be non-empty strings');

                continue;
            }

            if ($secret instanceof SecretRef) {
                $keyring[$id] = $secret;

                continue;
            }

            if (is_string($secret)) {
                $keyring[$id] = new SecretRef($secret);

                continue;
            }

            $violations->add('cursor.keyring.'.$id, 'must be a SecretRef');
        }

        ksort($keyring);

        return $keyring;
    }

    /** @return list<string> */
    private static function middleware(ConfigurationValidator $violations, mixed $value): array
    {
        if (! is_array($value)) {
            $violations->add('routes.middleware', 'must be a list of strings');

            return [];
        }

        $middleware = [];

        foreach ($value as $entry) {
            if (! is_string($entry) || $entry === '') {
                $violations->add('routes.middleware', 'must be a list of non-empty strings');

                return [];
            }

            $middleware[] = $entry;
        }

        return $middleware;
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

    private static function positiveInt(ConfigurationValidator $violations, string $path, mixed $value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        $violations->add($path, 'must be a positive integer');

        return 1;
    }
}
