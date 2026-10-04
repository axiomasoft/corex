<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal\Bootstrappers;

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\TenantContext;
use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
use CoreX\Tenancy\Runtime\Internal\FrameworkConfig;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Override;
use SessionHandlerInterface;

/**
 * Moves the live session handler to the account: database connection, per-account cache
 * prefix (redis/memcached), per-account directory (file). The Store object held by the
 * request, `session.store` and the auth guards stays the same; only its handler and the
 * session config change, and both are restored on revert (stored sessions are not deleted).
 */
final class SessionConnectionBootstrapper implements TenantBootstrapper
{
    public const array SUPPORTED_DRIVERS = ['database', 'redis', 'memcached', 'file'];

    /** @var array<array-key, mixed>|null */
    private ?array $original = null;

    /** @var array<string, SessionHandlerInterface> */
    private array $handlers = [];

    /** @var array<array-key, mixed>|null */
    private ?array $cacheOriginal = null;

    public function __construct(
        private readonly SessionManager $sessions,
        private readonly CacheManager $cache,
        private readonly Container $container,
        private readonly FrameworkConfig $config,
        private readonly string $centralConnection,
        private readonly Filesystem $files,
    ) {}

    #[Override]
    public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void
    {
        $this->original = (array) $this->config->get('session');
        $driver = $this->original['driver'] ?? null;

        if ($driver === null) {
            return;
        }

        if (! in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            throw new InvalidConfiguration(['corex-tenancy.session: unsupported driver']);
        }

        foreach ($this->createdStores() as $name => $store) {
            $this->handlers[$name] = $store->getHandler();
        }

        $suffix = hash('sha256', $context->account->id);

        if ($driver === 'database') {
            $this->config->set('session.connection', 'tenant');
        } elseif ($driver === 'file') {
            $root = $this->original['files'] ?? null;

            if (! is_string($root) || $root === '') {
                throw new InvalidConfiguration(['corex-tenancy.session.files: file sessions need a directory']);
            }

            $path = rtrim($root, '/').'/corex-tenants/'.$suffix;
            $this->files->ensureDirectoryExists($path, 0700, true);

            if (! $this->files->isDirectory($path) || ! $this->files->isWritable($path)) {
                throw new InvalidConfiguration(['corex-tenancy.session.files: directory is not writable']);
            }

            $this->config->set('session.files', $path);
        } else {
            $name = $this->original['store'] ?? null;

            if (! is_string($name) || $name === '') {
                throw new InvalidConfiguration(['corex-tenancy.session.store: required for redis/memcached sessions']);
            }

            $this->cacheOriginal = (array) $this->config->get("cache.stores.{$name}");
            $existing = $this->cacheOriginal['prefix'] ?? $this->config->get('cache.prefix', '');
            $prefix = 'corex-session:'.$suffix.':'.(is_string($existing) ? $existing : '');
            $this->config->set("cache.stores.{$name}.prefix", $prefix);

            if ($driver === 'redis') {
                $this->config->set('session.prefix', $prefix); // Laravel otherwise overrides the cache prefix
            }

            $this->cache->forgetDriver($name);
        }

        // Keep the Store object held by request/session.store/Auth; replace its handler.
        // A fresh manager respects database, cache clone, Redis connection/prefix and file settings.
        $created = $this->createdStores();

        if (isset($created[$driver])) {
            $created[$driver]->setHandler($this->freshHandler($driver));
        }
    }

    #[Override]
    public function revert(): void
    {
        if ($this->original === null) {
            return;
        }

        $this->config->set('session', $this->original);

        if ($this->cacheOriginal !== null) {
            $name = $this->original['store'];
            \assert(\is_string($name));
            $this->config->set("cache.stores.{$name}", $this->cacheOriginal);
            $this->cache->forgetDriver($name);
        }

        // The connection bootstrapper reverts later, so a null connection must resolve explicitly to central here.
        if (($this->original['driver'] ?? null) === 'database') {
            $this->config->set('session.connection', $this->original['connection'] ?? $this->centralConnection);
        }

        foreach ($this->createdStores() as $name => $store) {
            $store->setHandler($this->handlers[$name] ?? $this->freshHandler($name));
        }

        $this->config->set('session', $this->original);
        $this->original = null;
        $this->handlers = [];
        $this->cacheOriginal = null;
    }

    /** @return array<string, Store> */
    private function createdStores(): array
    {
        /** @var array<string, Store> $stores */
        $stores = $this->sessions->getDrivers();

        return $stores;
    }

    private function freshHandler(string $driver): SessionHandlerInterface
    {
        /** @var Store $store */
        $store = (new SessionManager($this->container))->driver($driver);

        return $store->getHandler();
    }
}
