<?php

declare(strict_types=1);

use Aws\S3\S3Client;
use Composer\Autoload\ClassLoader;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Storage\Exceptions\StorageCapabilityUnavailable;
use CoreX\Tenancy\Runtime\Internal\Storage\ObjectTenantStorage;
use Illuminate\Container\Container;
use Illuminate\Filesystem\FilesystemManager;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;

$original = require $argv[1];
// A child process models Composer installation without the optional SDK.
// Clone the installed maps; do not mutate vendor files or the parent's loader.
$withoutSdk = static fn (string $name): bool => ! str_starts_with($name, 'Aws\\')
    && ! str_starts_with($name, 'League\\Flysystem\\AwsS3V3\\');
foreach (ClassLoader::getRegisteredLoaders() as $registered) {
    $registered->unregister();
}
$loader = new ClassLoader;
foreach ($original->getPrefixesPsr4() as $namespace => $paths) {
    if ($withoutSdk($namespace)) {
        $loader->setPsr4($namespace, $paths);
    }
}
$loader->addClassMap(array_filter($original->getClassMap(), $withoutSdk, ARRAY_FILTER_USE_KEY));
$loader->register();

if (class_exists(AwsS3V3Adapter::class) || class_exists(S3Client::class)) {
    throw new RuntimeException('Optional SDK isolation failed.');
}
$contexts = new class implements TenantContextResolver
{
    public function current(): TenantContext
    {
        throw new LogicException('SDK denial must precede context access.');
    }
};

try {
    new ObjectTenantStorage($contexts, new FilesystemManager(new Container),
        ['driver' => 's3', 'bucket' => 'unconfigured'], sys_get_temp_dir().'/unused-corex-sdk-absence');
} catch (StorageCapabilityUnavailable) {
    echo "sdk-absent: denied before context or storage access\n";
    exit(0);
}

throw new RuntimeException('Missing SDK was accepted.');
