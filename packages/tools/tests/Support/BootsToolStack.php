<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Support;

use CoreX\CoreServiceProvider;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Tools\ToolsServiceProvider;

trait BootsToolStack
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, OperationsServiceProvider::class, ToolsServiceProvider::class];
    }
}
