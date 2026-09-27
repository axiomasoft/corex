<?php

declare(strict_types=1);

namespace CoreX\Auth\Tests;

use CoreX\Auth\Tests\Support\CoreAuthTestCase;

abstract class TestCase extends CoreAuthTestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
