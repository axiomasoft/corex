<?php

declare(strict_types=1);

namespace CoreX\Tasks\Testing;

use CoreX\Modules\ModulesServiceProvider;
use CoreX\Tasks\TasksServiceProvider;
use CoreX\Testing\CoreXTestCase;
use Illuminate\Foundation\Application;

/**
 * Testbench base for Tasks suites: core's environment plus the Tasks provider.
 */
abstract class TasksTestCase extends CoreXTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ...parent::getPackageProviders($app),
            ModulesServiceProvider::class,
            TasksServiceProvider::class,
        ];
    }
}
