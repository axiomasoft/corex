<?php

declare(strict_types=1);

use CoreX\Auth\Matrix\ScopeMapVersion;
use CoreX\Tenancy\SingleAccountResolver;
use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$container = new Container;
Container::setInstance($container);
$container->instance('config', new Repository([
    'cache' => ['default' => 'file', 'stores' => ['file' => ['driver' => 'file', 'path' => $argv[1]]]],
]));
$container->instance('files', new Filesystem);
$versions = new ScopeMapVersion(new SingleAccountResolver, new CacheManager($container));
for ($iteration = 0; $iteration < 100; $iteration++) {
    $versions->bump('concurrent-role');
}
