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
    // discovery: Testbench laravel/framework replaces illuminate/http; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/http', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/support; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/support', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/validation; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/validation', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Pest loads local test classes and late use aliases when executing the recorded test files
    ->ignoreUnknownClasses(['CoreX\\Webhooks\\Internal\\CurlRuntimeFixture'])
    // discovery: Testbench laravel/framework replaces illuminate/auth; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/auth', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/console; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/console', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/contracts; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/contracts', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/database; shipped component imports retain their standalone constraint
    ->ignoreErrorsOnPackage('illuminate/database', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: src/Webhooks/Internal/CurlWebhookTransport.php guards curl_init and returns capability_unavailable when absent
    ->ignoreErrorsOnExtension('ext-curl', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
