<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\ScopeAuthority;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use Illuminate\Contracts\Config\Repository;

final readonly class FieldScenarioRoute implements AccountConnectionResolver
{
    public function __construct(private ScopeAuthority $authority, private Repository $configuration) {}

    public function resolve(AccountRef $account): DatabaseConnectionConfig
    {
        $route = $this->configuration->get('database.connections.pgsql');
        $target = getenv('COREX_SCENARIO_DB_A');
        $approved = preg_match('/\Acorex_scenario_[0-9a-f]+_a_test\z/D', (string) $target) === 1;

        if ($account->id !== Scenario::BOXED || ! $approved || ($route['database'] ?? null) !== $target
            || ($route['driver'] ?? null) !== 'pgsql' || ($route['host'] ?? null) !== getenv('PGSQL_HOST')
            || (int) ($route['port'] ?? 0) !== (int) getenv('PGSQL_PORT') || ($route['username'] ?? null) !== getenv('PGSQL_USERNAME')) {
            throw new ScopeContextMismatch('The fixture host did not provision this PostgreSQL writer.');
        }
        $binding = $this->authority->resolve($account);
        $options = [
            'corex_fields_ddl_namespace' => 'public',
            'deployment_id' => $binding->deploymentId,
            'binding_id' => $binding->bindingId,
            'binding_generation' => $binding->bindingGeneration,
            'restore_epoch' => $binding->restoreEpoch,
        ];

        if (getenv('COREX_SCENARIO_DDL_UNATTESTED') !== '1') {
            $options['corex_fields_ddl_transport'] = 'direct-v1';
        }

        return new DatabaseConnectionConfig('pgsql', $route['host'], (int) $route['port'], $target, $route['username'], $options);
    }
}
