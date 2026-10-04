<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal\Bootstrappers;

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\TenantContext;
use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
use CoreX\Tenancy\Runtime\Internal\FrameworkConfig;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ConfigurationUrlParser;
use Override;

/** Named connection `tenant` = central template, overlaid with the account's route; becomes the default. */
final class ConnectionBootstrapper implements TenantBootstrapper
{
    private const array ROUTING_KEYS = ['url', 'driver', 'host', 'port', 'database', 'username', 'read', 'write'];

    private ?string $previousDefault = null;

    /** @var array<array-key, mixed>|null */
    private ?array $previousTenant = null;

    private bool $captured = false;

    public function __construct(
        private readonly DatabaseManager $databases,
        private readonly FrameworkConfig $config,
        private readonly string $centralConnection,
    ) {}

    #[Override]
    public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void
    {
        $this->previousDefault = $this->databases->getDefaultConnection();
        $previousTenant = $this->config->get('database.connections.tenant');
        $this->previousTenant = is_array($previousTenant) ? $previousTenant : null;
        $this->captured = true;
        /** @var array<string, mixed> $central */
        $central = (array) $this->config->get("database.connections.{$this->centralConnection}");
        $base = (new ConfigurationUrlParser)->parseConfiguration($central);
        unset($base['url']);

        // A host resolver may override credentials/options, but never routing keys.
        if (array_intersect(self::ROUTING_KEYS, array_keys($database->options)) !== []) {
            throw new InvalidConfiguration(['corex-tenancy.connection: routing keys in options']);
        }

        // Replica overlays would otherwise override the authoritative account route.
        unset($base['read'], $base['write']);

        $this->config->set('database.connections.tenant', array_replace($base, [
            'driver' => $database->driver,
            'host' => $database->host,
            'port' => $database->port,
            'database' => $database->database,
            'username' => $database->username,
        ], $database->options));
        $this->databases->purge('tenant');
        $this->databases->setDefaultConnection('tenant');
    }

    #[Override]
    public function revert(): void
    {
        $this->databases->purge('tenant');
        $this->databases->setDefaultConnection($this->previousDefault ?? $this->centralConnection);

        if ($this->captured) {
            // Never leave the last account's route behind under the shared connection name.
            $this->config->set('database.connections.tenant', $this->previousTenant);
        }

        $this->previousDefault = null;
        $this->previousTenant = null;
        $this->captured = false;
    }
}
