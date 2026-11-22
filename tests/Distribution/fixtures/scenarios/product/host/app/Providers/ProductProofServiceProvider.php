<?php

declare(strict_types=1);

namespace App\Providers;

use App\ProductProof\CrmActor;
use App\ProductProof\CrmConnections;
use App\ProductProof\CrmPolicy;
use App\ProductProof\CrmRequest;
use App\ProductProof\EquipmentComposition;
use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use Flex\Crm\Context\Contracts\CrmExecutionContext;
use Illuminate\Support\ServiceProvider;

final class ProductProofServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $config = $this->app['config'];
        $config->set('database.connections.scenario_budget', [
            ...$config->get('database.connections.pgsql'),
            'database' => getenv('COREX_SCENARIO_DB_BUDGET'),
        ]);

        if (getenv('COREX_SCENARIO_VARIANT') === 'cloud') {
            $this->app->scoped(CrmExecutionContext::class, CrmActor::class);
            $this->app->scoped(AccountConnectionResolver::class, CrmConnections::class);
            $this->app->scoped(CrmPolicy::class);
            foreach ([OperationAuthorizer::class, OperationFieldAuthorizer::class, PrincipalResolver::class] as $contract) {
                $this->app->alias(CrmPolicy::class, $contract);
            }
            $config->set('corex-operations.routes.middleware', [CrmRequest::class]);
        } else {
            $this->app->register(EquipmentComposition::class);
        }
    }
}
