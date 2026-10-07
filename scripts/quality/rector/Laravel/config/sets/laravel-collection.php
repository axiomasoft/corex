<?php

declare(strict_types=1);
use CoreX\Tooling\Rector\Laravel\Rector\BooleanNot\AvoidNegatedCollectionContainsOrDoesntContainRector;
use CoreX\Tooling\Rector\Laravel\Rector\MethodCall\AvoidNegatedCollectionFilterOrRejectRector;
use CoreX\Tooling\Rector\Laravel\Rector\MethodCall\ConvertEnumerableToArrayToAllRector;
use CoreX\Tooling\Rector\Laravel\Rector\MethodCall\UnaliasCollectionMethodsRector;
use Rector\Config\RectorConfig;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->import(__DIR__.'/../config.php');
    $rectorConfig->rule(AvoidNegatedCollectionContainsOrDoesntContainRector::class);
    $rectorConfig->rule(AvoidNegatedCollectionFilterOrRejectRector::class);
    $rectorConfig->rule(ConvertEnumerableToArrayToAllRector::class);
    $rectorConfig->rule(UnaliasCollectionMethodsRector::class);
};
