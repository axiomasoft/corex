<?php

declare(strict_types=1);

namespace CoreX\Access\Tests;

use CoreX\Access\Tests\Support\CoreAuthTestCase;
use CoreX\Tests\Support\WorkScopeFixture;

abstract class TestCase extends CoreAuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WorkScopeFixture::install($this->app, principalId: 'access-fixture-operator')->scope();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
