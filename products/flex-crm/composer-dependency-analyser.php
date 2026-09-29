<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration)
    ->addPathToScan(__DIR__.'/../../products/flex-crm/src', isDev: false)
    ->addPathToScan(__DIR__.'/../../products/flex-crm/tests', isDev: true)
    ->addPathToScan(__DIR__.'/../../products/flex-crm/database', isDev: true);
