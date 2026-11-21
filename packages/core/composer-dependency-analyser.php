<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/database', isDev: true)
    ->addPathToScan(__DIR__.'/src/Messaging/PHPStan', isDev: true)
    ->addPathToScan(__DIR__.'/src/Foundation/Tooling', isDev: true)
    ->addPathToScan(__DIR__.'/src/Errors/Tooling', isDev: true)
    // discovery: src/Messaging/Commands/RelayOutbox.php extension_loaded guard
    ->ignoreErrorsOnExtension('ext-pcntl', [ErrorType::SHADOW_DEPENDENCY])
    // discovery: composer suggest; guarded optional adapter remains installable without open-telemetry/api
    ->ignoreErrorsOnPackage('open-telemetry/api', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // discovery: composer suggest; guarded optional adapter remains installable without psr/log
    ->ignoreErrorsOnPackage('psr/log', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // discovery: Testbench laravel/framework replaces illuminate/contracts; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/contracts', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/database; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/database', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/support; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/support', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Pest loads local test classes and late use aliases when executing the recorded test files
    ->ignoreUnknownClasses(['RemediationManifestListener'])
    // discovery: Testbench laravel/framework replaces illuminate/auth; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/auth', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/console; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/console', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/events; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/events', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/queue; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/queue', [ErrorType::UNUSED_DEPENDENCY]);
