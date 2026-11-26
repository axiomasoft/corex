<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/database', isDev: true)
    // discovery: src/Internal/GdThumbnailDriver.php extension_loaded guard
    ->ignoreErrorsOnExtension('ext-gd', [ErrorType::SHADOW_DEPENDENCY])
    // Shared monorepo fixtures from tests/Support, loaded by tests/Support/autoload.php; not a Composer package.
    ->ignoreUnknownClassesRegex('~^CoreX\\\\Tests\\\\Support\\\\~')
    // Declared inside the test files that use them; Pest loads them with those files.
    ->ignoreUnknownClasses(['MediaModulePurgeCase', 'OtherMediaInventoryModule']);
