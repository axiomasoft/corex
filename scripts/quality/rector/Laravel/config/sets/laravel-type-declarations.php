<?php

declare(strict_types=1);
use CoreX\Tooling\Rector\Laravel\Rector\ClassMethod\AddGenericBuilderToScopesRector;
use CoreX\Tooling\Rector\Laravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use CoreX\Tooling\Rector\Laravel\Rector\ClassMethod\AddLegacyGenericReturnTypeToRelationsRector;
use CoreX\Tooling\Rector\Laravel\Rector\ClassMethod\AddNewGenericReturnTypeToRelationsRector;
use CoreX\Tooling\Rector\Laravel\Rector\FuncCall\TypeHintTappableCallRector;
use CoreX\Tooling\Rector\Laravel\Rector\MethodCall\EloquentWhereRelationTypeHintingParameterRector;
use CoreX\Tooling\Rector\Laravel\Rector\MethodCall\EloquentWhereTypeHintClosureParameterRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->import(__DIR__.'/../config.php');
    $rectorConfig->rule(TypeHintTappableCallRector::class);
    // one of the three runs, picked by the installed laravel/framework version via composer constraint
    $rectorConfig->rule(AddLegacyGenericReturnTypeToRelationsRector::class);
    $rectorConfig->rule(AddNewGenericReturnTypeToRelationsRector::class);
    $rectorConfig->rule(AddGenericReturnTypeToRelationsRector::class);
    $rectorConfig->rule(AddGenericBuilderToScopesRector::class);
    $rectorConfig->rule(EloquentWhereRelationTypeHintingParameterRector::class);
    $rectorConfig->rule(EloquentWhereTypeHintClosureParameterRector::class);
};
