<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    // discovery: standalone Testbench laravel/framework replaces the declared Illuminate component symbols
    ->ignoreErrorsOnPackage('laravel/framework', [ErrorType::SHADOW_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/contracts; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/contracts', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/database; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/database', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/support; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/support', [ErrorType::UNUSED_DEPENDENCY])
    // Shared monorepo fixtures from tests/Support, loaded by tests/Support/autoload.php; not a Composer package.
    ->ignoreUnknownClassesRegex('~^CoreX\\\\Tests\\\\Support\\\\~')
    // The PostgreSQL case reuses Custom Fields' PG test case; the root suite provides it through autoload-dev.
    ->ignoreUnknownClasses(['CoreX\\CustomFields\\Tests\\Pg\\SchemaPgTestCase']);
