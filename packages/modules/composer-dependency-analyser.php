<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/database', isDev: true)
    ->addPathToScan(__DIR__.'/src/PHPStan', isDev: true)
    // discovery: standalone Testbench installs laravel/framework, which replaces the declared illuminate components
    ->ignoreErrorsOnPackage('laravel/framework', [ErrorType::SHADOW_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/contracts; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/contracts', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/database; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/database', [ErrorType::UNUSED_DEPENDENCY])
    // discovery: Testbench laravel/framework replaces illuminate/support; shipped Illuminate imports/helpers use this component
    ->ignoreErrorsOnPackage('illuminate/support', [ErrorType::UNUSED_DEPENDENCY])
    // ratchet: P09 standalone module tests still reference monorepo tests/Stubs (CoreX\Tests\Stubs\Lifecycle\StubBaseModule)
    ->ignoreUnknownClasses(['CoreX\\Tests\\Stubs\\Lifecycle\\StubBaseModule'])
    // ratchet: P09 standalone module tests still reference monorepo tests/Stubs (CoreX\Tests\Stubs\Lifecycle\StubFailingModule)
    ->ignoreUnknownClasses(['CoreX\\Tests\\Stubs\\Lifecycle\\StubFailingModule'])
    // ratchet: P09 standalone module tests still reference monorepo tests/Stubs (CoreX\Tests\Stubs\Lifecycle\StubFeatureFlagModule)
    ->ignoreUnknownClasses(['CoreX\\Tests\\Stubs\\Lifecycle\\StubFeatureFlagModule'])
    // ratchet: P09 standalone module tests still reference monorepo tests/Stubs (CoreX\Tests\Stubs\Widget)
    ->ignoreUnknownClasses(['CoreX\\Tests\\Stubs\\Widget'])
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
