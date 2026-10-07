<?php

declare(strict_types=1);

use CoreX\Tooling\Rector\Laravel\Rector\ArrayDimFetch\ServerVariableToRequestFacadeRector;
use CoreX\Tooling\Rector\Laravel\Rector\StaticCall\DispatchToHelperFunctionsRector;
use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;

return RectorConfig::configure()
    // Forked PHP workers crash on this toolchain; keep the same full rule/file scope.
    ->withoutParallel()
    ->withPaths([
        ...glob(__DIR__.'/packages/*/src', GLOB_ONLYDIR),
        ...glob(__DIR__.'/packages/*/tests', GLOB_ONLYDIR),
        ...glob(__DIR__.'/products/*/src', GLOB_ONLYDIR),
        ...glob(__DIR__.'/products/*/tests', GLOB_ONLYDIR),
        __DIR__.'/tests',
        __DIR__.'/scripts',
    ])
    ->withSkip([
        ...glob(__DIR__.'/packages/*/database/migrations', GLOB_ONLYDIR),
        ...glob(__DIR__.'/products/*/database/migrations', GLOB_ONLYDIR),
        ...glob(__DIR__.'/packages/*/tests/Fixtures/vendor', GLOB_ONLYDIR),
    ])
    ->withSets([
        __DIR__.'/scripts/quality/rector/Laravel/config/sets/laravel-code-quality.php',
        __DIR__.'/scripts/quality/rector/Laravel/config/sets/laravel-collection.php',
        __DIR__.'/scripts/quality/rector/Laravel/config/sets/laravel-type-declarations.php',
    ])
    ->withPhpSets(php84: true)
    ->withConfiguredRule(DispatchToHelperFunctionsRector::class, [
        DispatchToHelperFunctionsRector::PRESERVE_PATHS => [
            ...glob(__DIR__.'/packages/*/src', GLOB_ONLYDIR),
            ...glob(__DIR__.'/products/*/src', GLOB_ONLYDIR),
        ],
    ])
    ->withConfiguredRule(ServerVariableToRequestFacadeRector::class, [
        ServerVariableToRequestFacadeRector::PRESERVE_PATHS => [
            __DIR__.'/packages/core/src/Modules/Laravel/ModulesBootRegistrar.php',
        ],
    ])
    ->withConfiguredRule(ClassPropertyAssignToConstructorPromotionRector::class, [
        ClassPropertyAssignToConstructorPromotionRector::RENAME_PROPERTY => false,
    ])
    // These names are inspection data. Importing them crosses package or Internal boundaries.
    ->withConfiguredRule(StringClassNameToClassConstantRector::class, [
        // Optional container binding identifier; importing it creates a production/dev dependency edge.
        'Laravel\\Octane\\Octane',
        'Symfony\\Component\\HttpFoundation\\Request',
        'Psr\\Container\\ContainerInterface',
        'Illuminate\\Contracts\\Container\\Container',
        'Illuminate\\Container\\Container',
        'CoreX\\Transfer\\Contracts\\TransferRunner',
        'CoreX\\Wakeup\\WakeupServiceProvider',
        'CoreX\\Wakeup\\Contracts\\DueWakeupSweeper',
        'Illuminate\\Database\\Eloquent\\Model',
        'Illuminate\\Http\\Request',
        'CoreX\\Context\\Contracts\\TenancyManager',
        'CoreX\\Context\\Internal\\NullTenancyManager',
        'CoreX\\CustomFields\\Contracts\\FieldCatalogue',
        'CoreX\\CustomFields\\Internal\\Integration\\AttributeMessageContractRegistry',
        'CoreX\\CustomFields\\Internal\\Schema\\CachedFieldCatalogue',
        'CoreX\\CustomFields\\Internal\\Schema\\CompositeFieldCatalogue',
        'CoreX\\CustomFields\\Internal\\Schema\\DatabaseFieldCatalogue',
        'CoreX\\CustomFields\\Internal\\Schema\\ModuleFieldCatalogue',
        'CoreX\\CustomFields\\Internal\\Schema\\StaticFieldCatalogue',
        'CoreX\\CustomFields\\Internal\\Values\\AttributeValueTransformerRegistry',
        'CoreX\\Errors\\Contracts\\ProblemRegistry',
        'CoreX\\Errors\\Internal\\CompiledProblemRegistry',
        'CoreX\\Messaging\\Contracts\\MessageContractRegistry',
        'CoreX\\Messaging\\Internal\\ArrayMessageContractRegistry',
        'CoreX\\Modules\\Contracts\\EntityRegistry',
        'CoreX\\Modules\\Contracts\\FieldCastRegistry',
        'CoreX\\Modules\\Contracts\\ModuleRegistry',
        'CoreX\\Modules\\Data\\CompiledRegistry',
        'CoreX\\Modules\\Internal\\InMemoryEntityRegistry',
        'CoreX\\Modules\\Internal\\Registry\\CompiledModuleRegistry',
        'CoreX\\Modules\\Testing\\ArrayEntityRegistry',
        'CoreX\\Operations\\Definition\\Contracts\\OperationRegistry',
        'CoreX\\Operations\\Definition\\Internal\\OperationRegistry',
        'CoreX\\Operations\\Errors\\Exceptions\\ApiError',
        'CoreX\\Operations\\Pagination\\Contracts\\OrderingRegistry',
        'CoreX\\Operations\\Pagination\\Internal\\OrderingRegistry',
        'CoreX\\Panels\\Contracts\\PanelCatalogue',
        'CoreX\\Panels\\Internal\\ModulePanelCatalogue',
        'CoreX\\Search\\Contracts\\SearchDriverRegistry',
        'CoreX\\Search\\Internal\\ManagerDriverRegistry',
        'CoreX\\Search\\Internal\\SearchManager',
        'CoreX\\Tenancy\\Contracts\\TenantContext',
        'CoreX\\Tenancy\\Provisioning\\Internal\\Database\\TemplateClonePostgreSQLManager',
        'CoreX\\Tenancy\\Runtime\\Internal\\DatabaseTenancyManager',
        'CoreX\\Tools\\Contracts\\ToolCatalogue',
        'CoreX\\Tools\\Internal\\CompiledToolCatalogue',
        'CoreX\\Wakeup\\Internal\\WakeupHandlerRegistry',
        'CoreX\\Webhooks\\Internal\\WebhookTopicCatalogue',
    ])
    ->withImportNames(removeUnusedImports: true);
