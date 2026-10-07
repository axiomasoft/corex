<?php

declare(strict_types=1);

use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Matrix\Internal\ScopeMapVersion;
use CoreX\Context\Internal\SingleAccountResolver;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;

require $argv[2];

$container = new Container;
Container::setInstance($container);
$container->instance('config', new Repository([
    'cache' => ['default' => 'file', 'stores' => ['file' => ['driver' => 'file', 'path' => $argv[1]]]],
]));
$container->instance('files', new Filesystem);
$versions = new ScopeMapVersion(
    new SingleAccountResolver,
    new CacheManager($container),
    AuthOptions::fromArray(['cache' => ['store' => 'file']]),
);
for ($iteration = 0; $iteration < 100; $iteration++) {
    $versions->bump('concurrent-role');
}
