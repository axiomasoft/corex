<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\Connection;
use Packman\Attributes\Internal;
use PDO;
use stdClass;

#[Internal]
final readonly class DirectDdlRoute
{
    public function __construct(private CurrentWorkScope $current, private ?AccountConnectionResolver $routes, private bool $enabled) {}

    public function namespace(Connection $db, DataScope $scope): string
    {
        $context = $this->current->assert($scope, $db);
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
        foreach (['driver', 'host', 'port', 'database', 'username'] as $column) {
            if ((string) $route->{$column} !== $db->getConfig($column) && $route->{$column} !== $db->getConfig($column)) {
                throw new SchemaConflict('direct-ddl-endpoint-mismatch');
            }
        }
        foreach (['url', 'read', 'write', 'connect_via_database', 'connect_via_port'] as $option) {
            if ($db->getConfig($option) !== null) {
                throw new SchemaConflict('direct-ddl-transport-override');
            }
        }
        $options = $db->getConfig('options');

        if ((is_array($options) && ! in_array($options[PDO::ATTR_PERSISTENT] ?? false, [false, 0, '0', ''], true)) || $db->getTablePrefix() !== '') {
            throw new SchemaConflict('direct-ddl-transport-override');
        }
        $row = $db->selectOne(<<<'SQL'
            SELECT current_database() AS db, current_user AS owner, current_schema() AS namespace, pg_is_in_recovery() AS replica,
              current_setting('standard_conforming_strings') AS standard_strings,
              NOT EXISTS (SELECT 1 FROM pg_namespace n, LATERAL aclexplode(coalesce(n.nspacl,acldefault('n',n.nspowner))) p
                          WHERE n.nspname=? AND p.grantee NOT IN (n.nspowner, current_user::regrole::oid) AND p.privilege_type='CREATE') AS protected_namespace
            SQL, [$namespace], false);

        if (! $row instanceof stdClass || $row->db !== $route->database || $row->owner !== $route->username
            || $row->namespace !== $namespace || $row->replica !== false || $row->standard_strings !== 'on' || $row->protected_namespace !== true) {
            throw new SchemaConflict('direct-ddl-physical-owner-mismatch');
        }

        return $namespace;
    }
}
