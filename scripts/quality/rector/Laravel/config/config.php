<?php

declare(strict_types=1);
use CoreX\Tooling\Rector\Laravel\NodeVisitor\ArrayDimFetchContextNodeVisitor;
use CoreX\Tooling\Rector\Laravel\NodeVisitor\RandomEnumContextNodeVisitor;
use Rector\Config\RectorConfig;

/**
 * to be imported, don't use RectorConfigBuilder for safe usage
 */
return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->singleton(ArrayDimFetchContextNodeVisitor::class);
    $rectorConfig->singleton(RandomEnumContextNodeVisitor::class);
};
