<?php

declare(strict_types=1);

namespace Flex\Crm\Testing;

use CoreX\Modules\ModulesServiceProvider;
use CoreX\Testing\CoreXTestCase;
use Flex\Crm\CrmServiceProvider;
use Illuminate\Foundation\Application;

/**
 * Testbench base for CRM suites: core's environment plus the CRM provider.
 */
abstract class CrmTestCase extends CoreXTestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ModulesServiceProvider::class,
            CrmServiceProvider::class,
        ];
    }
}
