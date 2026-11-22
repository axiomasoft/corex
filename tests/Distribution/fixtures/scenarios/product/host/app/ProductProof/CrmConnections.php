<?php

declare(strict_types=1);

namespace App\ProductProof;

use App\Scenario\CloudTopology;
use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\ScopeAuthority;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use Illuminate\Contracts\Config\Repository;

final readonly class CrmConnections implements AccountConnectionResolver
{
    public function __construct(private ScopeAuthority $authority, private CloudTopology $topology, private Repository $config) {}

    public function resolve(AccountRef $account): DatabaseConnectionConfig
    {
        $binding = $this->authority->resolve($account);
        $role = $this->topology->location($binding);
        $route = $this->config->get('database.connections.scenario_'.$role);

        return new DatabaseConnectionConfig(
            driver: $route['driver'],
            host: $route['host'],
            port: (int) $route['port'],
            database: $route['database'],
            username: $route['username'],
        );
    }
}
