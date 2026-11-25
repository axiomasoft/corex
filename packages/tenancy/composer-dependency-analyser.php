<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration)
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/database', isDev: true)
    // discovery: src/Runtime/Storage/ObjectTenantStorage.php class_exists guard; optional S3 adapter
    ->ignoreErrorsOnPackage('aws/aws-sdk-php', [ErrorType::SHADOW_DEPENDENCY])
    // discovery: composer suggest; guarded optional adapter remains installable without league/flysystem-aws-s3-v3
    ->ignoreErrorsOnPackage('league/flysystem-aws-s3-v3', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // discovery: Pest loads local test classes and late use aliases when executing the recorded test files
    ->ignoreUnknownClasses(['ImpersonationGuardWriteStub', 'WorkspaceScopedRecordStub'])
    // Shared monorepo fixtures from tests/Support, loaded by tests/Support/autoload.php; not a Composer package.
    ->ignoreUnknownClassesRegex('~^CoreX\\\\Tests\\\\Support\\\\~');
