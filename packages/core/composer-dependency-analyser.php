<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/database', isDev: true)
    ->addPathToScan(__DIR__.'/src/PHPStan', isDev: true)
    // discovery: src/Messaging/Commands/RelayOutbox.php extension_loaded guard
    ->ignoreErrorsOnExtension('ext-pcntl', [ErrorType::SHADOW_DEPENDENCY])
    // discovery: composer suggest; guarded optional adapter remains installable without open-telemetry/api
    ->ignoreErrorsOnPackage('open-telemetry/api', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // discovery: composer suggest; guarded optional adapter remains installable without psr/log
    ->ignoreErrorsOnPackage('psr/log', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
