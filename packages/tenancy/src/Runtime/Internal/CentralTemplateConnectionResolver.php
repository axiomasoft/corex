<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\ConfigurationUrlParser;
use Override;

/**
 * Stock {@see AccountConnectionResolver}: the central connection's parameters (URL parsed,
 * credentials and options carried) with `database` = `root_accounts.db_name`. A host may
 * rebind the port to a cluster-aware implementation.
 */
final readonly class CentralTemplateConnectionResolver implements AccountConnectionResolver
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private FrameworkConfig $config,
        private string $centralConnection,
    ) {}

    #[Override]
    public function resolve(AccountRef $account): DatabaseConnectionConfig
    {
        $database = $this->connections->connection($this->centralConnection)
            ->table('root_accounts')->where('id', $account->id)->value('db_name');

        if (! is_string($database) || $database === '') {
            throw TenantContextUnavailable::missingDatabase($account->id);
        }

        /** @var array<string, mixed> $raw */
        $raw = (array) $this->config->get("database.connections.{$this->centralConnection}");
        $central = (new ConfigurationUrlParser)->parseConfiguration($raw);
        unset($central['url']);

        $driver = $central['driver'] ?? null;

        if (! is_string($driver) || $driver === '') {
            throw new InvalidConfiguration(["database.connections.{$this->centralConnection}.driver: required"]);
        }

        return new DatabaseConnectionConfig(
            driver: $driver,
            host: self::text($central['host'] ?? null),
            port: self::port($central['port'] ?? null),
            database: $database,
            username: self::text($central['username'] ?? null),
            options: self::scalarOptions(array_diff_key($central, array_flip(['driver', 'host', 'port', 'database', 'username', 'read', 'write']))),
        );
    }

    /**
     * The connection DTO carries scalar overrides only (password, schema, sslmode, …); structured
     * keys such as PDO `options` stay in the central template the connection bootstrapper starts from.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, bool|float|int|string|null>
     */
    private static function scalarOptions(array $options): array
    {
        return array_filter($options, static fn (mixed $value): bool => is_scalar($value) || $value === null);
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function port(mixed $value): int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : 5432;
    }
}
