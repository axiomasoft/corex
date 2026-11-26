<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration)
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/database', isDev: true)
    // Shared monorepo fixtures from tests/Support, loaded by tests/Support/autoload.php; not a Composer package.
    ->ignoreUnknownClassesRegex('~^CoreX\\\\Tests\\\\Support\\\\~');
