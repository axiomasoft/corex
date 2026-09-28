<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/database', isDev: true)
    // discovery: standalone Testbench installs laravel/framework, which replaces the declared illuminate components
    ->ignoreErrorsOnPackage('laravel/framework', [ErrorType::SHADOW_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/contracts; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/contracts', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/support; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/support', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/auth; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/auth', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/database; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/database', [ErrorType::UNUSED_DEPENDENCY]);
