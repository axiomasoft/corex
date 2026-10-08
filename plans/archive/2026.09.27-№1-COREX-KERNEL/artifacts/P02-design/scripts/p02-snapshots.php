<?php

declare(strict_types=1);
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Operations\Testing\ModuleFixture;
use CoreX\Operations\Tests\Feature\ContractSnapshotTest;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\TextUI\CliArguments\Builder;
use PHPUnit\TextUI\Configuration\Registry;
use PHPUnit\TextUI\XmlConfiguration\Loader;

require 'vendor/autoload.php';
Registry::init(
    (new Builder)->fromParameters(['phpunit']),
    (new Loader)->load('phpunit.xml'),
);
$case = new ContractSnapshotTest('test_generation_is_byte_stable_and_check_detects_drift');
(new ReflectionMethod($case, 'setUp'))->invoke($case);
$registry = (new ReflectionMethod($case, 'compile'))->invoke($case, false);
$app = Container::getInstance();
$app->instance(ModuleRegistry::class, new ModuleFixture($registry));
$status = $app->make(Kernel::class)->call('corex:api-snapshots', [
    'directory' => getcwd().'/build/p02/api-projection',
    '--fixture' => getcwd().'/tests/fixtures/public-error-contract.php',
]);
(new ReflectionMethod($case, 'tearDown'))->invoke($case);
exit($status);
