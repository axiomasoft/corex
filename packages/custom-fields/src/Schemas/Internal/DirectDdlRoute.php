<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use Closure;
use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\Connection;
use Packman\Attributes\Internal;
use PDO;
use stdClass;

#[Internal]
final readonly class DirectDdlRoute
{
    /** @param (Closure(string):mixed)|null $declaredConnection Original host declaration, before Laravel normalizes URL and read/write settings. */
    public function __construct(private CurrentWorkScope $current, private ?AccountConnectionResolver $routes, private bool $enabled,
        private ?Closure $declaredConnection = null) {}

    public function namespace(Connection $db, DataScope $scope): string
    {
        $context = $this->current->assert(scope: $scope, connection: $db);
        $route = $this->routes?->resolve($context->account);

        if (! $this->enabled || $db->getDriverName() !== 'pgsql' || $route === null || ($route->options['corex_fields_ddl_transport'] ?? '') !== 'direct-v1') {
            throw new SchemaConflict('direct-ddl-route-unavailable');
        }
        $namespace = $route->options['corex_fields_ddl_namespace'] ?? null;

        if (! is_string($namespace) || preg_match('/\A[a-z_][a-z0-9_]{0,62}\z/D', $namespace) !== 1) {
            throw new SchemaConflict('direct-ddl-namespace-unavailable');
        }
        foreach (SchemaRows::fence($scope) as $column => $expected) {
            if (($route->options[$column] ?? null) !== $expected) {
                throw new SchemaConflict('direct-ddl-fence-stale');
            }
        }
        $name = $db->getConfig('name');

        if ($db->getNameWithReadWriteType() !== $name || $db->getRawReadPdo() !== null || $db->getRawDirectPdo() !== null || $db->getTablePrefix() !== '') {
            throw new SchemaConflict('direct-ddl-transport-override');
        }
        $declared = is_string($name) && $this->declaredConnection !== null ? ($this->declaredConnection)($name) : null;
        $this->assertTransport(config: $declared, route: $route);
        $this->assertTransport(config: $db->getConfig(), route: $route);

        $row = $db->selectOne(<<<'SQL'
            SELECT pg_catalog.current_database() AS db, current_user AS owner, session_user AS login,
              pg_catalog.current_schema() AS namespace, pg_catalog.pg_is_in_recovery() AS replica,
              pg_catalog.current_setting('standard_conforming_strings') AS standard_strings,
              NOT EXISTS (SELECT 1 FROM pg_catalog.pg_namespace n, LATERAL pg_catalog.aclexplode(coalesce(n.nspacl,pg_catalog.acldefault('n',n.nspowner))) p
                          WHERE n.nspname=? AND p.grantee NOT IN (n.nspowner, current_user::regrole::oid) AND p.privilege_type='CREATE') AS protected_namespace
            SQL, [$namespace], false);

        if (! $row instanceof stdClass || $row->db !== $route->database || $row->owner !== $route->username || $row->login !== $route->username
            || $row->namespace !== $namespace || $row->replica !== false || $row->standard_strings !== 'on' || $row->protected_namespace !== true) {
            throw new SchemaConflict('direct-ddl-physical-owner-mismatch');
        }

        return $namespace;
    }

    private function assertTransport(mixed $config, DatabaseConnectionConfig $route): void
    {
        if (! is_array($config)) {
            throw new SchemaConflict('direct-ddl-route-unavailable');
        }
        foreach (['url', 'read', 'write', 'direct', 'connect_via_database', 'connect_via_port'] as $option) {
            if (isset($config[$option])) {
                throw new SchemaConflict('direct-ddl-transport-override');
            }
        }
        $options = $config['options'] ?? [];

        if (($config['pooled'] ?? false) !== false || ! is_array($options) || ! in_array($options[PDO::ATTR_PERSISTENT] ?? false, [false, 0, '0', ''], true)) {
            throw new SchemaConflict('direct-ddl-transport-override');
        }
        foreach (['driver', 'host', 'port', 'database', 'username'] as $column) {
            if (! in_array($config[$column] ?? null, [$route->{$column}, (string) $route->{$column}], true)) {
                throw new SchemaConflict('direct-ddl-endpoint-mismatch');
            }
        }
    }
}
