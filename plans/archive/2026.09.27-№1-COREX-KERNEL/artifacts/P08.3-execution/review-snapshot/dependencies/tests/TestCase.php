<?php

declare(strict_types=1);

namespace CoreX\Tests;

use CoreX\Testing\CoreXTestCase;

abstract class TestCase extends CoreXTestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
