<?php

declare(strict_types=1);

use Composer\InstalledVersions;
use CoreX\CoreServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->configurationIsCached() || ! isset($app->getLoadedProviders()[CoreServiceProvider::class])) {
    throw new RuntimeException('Cached Laravel configuration or CoreX provider is missing.');
}

if (InstalledVersions::isInstalled('axiomasoft/corex-tenancy')) {
    throw new RuntimeException('The bare consumer installed Tenancy.');
}

$connection = DB::connection();
$pdo = $connection->getPdo();
$before = $pdo->query('SELECT 1')->fetchColumn();

$barrier = getenv('COREX_SCENARIO_BARRIERS').'/booted';
file_put_contents($barrier.'.reached', (string) getmypid(), LOCK_EX);
while (! is_file($barrier.'.release')) {
    usleep(25_000);
}

$after = $pdo->query('SELECT 1')->fetchColumn();

if ($before !== 1 || $after !== 1) {
    throw new RuntimeException('The Laravel connection failed its query before or after the barrier.');
}

echo json_encode([
    'pid' => getmypid(),
    'assertions' => 5,
    'backend' => $pdo->query('SELECT sqlite_version()')->fetchColumn(),
    'connection' => $connection->getDatabaseName(),
    'refs' => [InstalledVersions::getReference('axiomasoft/corex')],
    'before' => $before,
    'after' => $after,
], JSON_THROW_ON_ERROR);
