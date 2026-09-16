<?php

declare(strict_types=1);

use CoreX\Media\MediaModule;
use CoreX\Notifications\NotificationsModule;
use CoreX\Panels\PanelsModule;
use CoreX\Wakeup\WakeupModule;

require __DIR__.'/vendor/autoload.php';

$classes = [
    'CoreX\\CoreServiceProvider',
    'CoreX\\Modules\\ModulesServiceProvider',
    'CoreX\\Panels\\PanelsServiceProvider',
    'CoreX\\Media\\MediaServiceProvider',
    'CoreX\\Wakeup\\WakeupServiceProvider',
    'CoreX\\Notifications\\NotificationsServiceProvider',
];

$autoload = [];
foreach ($classes as $class) {
    $file = (new ReflectionClass($class))->getFileName();

    if (! is_string($file) || ! str_starts_with(realpath($file), realpath(__DIR__.'/vendor').DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Autoload escaped installed vendor: '.$class);
    }

    $autoload[$class] = substr(realpath($file), strlen(realpath(__DIR__)) + 1);
}

$manifests = [];
foreach ([new PanelsModule, new MediaModule, new WakeupModule, new NotificationsModule] as $module) {
    $manifest = $module->manifest();
    $manifests[$manifest->composerName] = $manifest->tablePrefix;
}

if ($manifests !== [
    'corex/panels' => 'pnl',
    'corex/media' => 'med',
    'corex/wakeup' => 'sys',
    'corex/notifications' => 'ntf',
]) {
    throw new RuntimeException('Installed module manifests differ from the qualified profile.');
}

echo json_encode([
    'autoload' => $autoload,
    'manifests' => $manifests,
    'php' => PHP_VERSION,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
