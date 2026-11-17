<?php

declare(strict_types=1);

// Resolve the Laravel matrix while keeping analysis rules bound to the reviewed lockfile.
// Upgrading this toolchain requires a lockfile change, just as it does locally.
$lock = json_decode((string) file_get_contents(__DIR__.'/../composer.lock'), true, flags: JSON_THROW_ON_ERROR);

if (! is_array($lock) || ! is_array($lock['packages'] ?? null) || ! is_array($lock['packages-dev'] ?? null)) {
    fwrite(STDERR, "Invalid Composer lockfile package inventory\n");
    exit(2);
}

$tools = json_decode((string) file_get_contents(__DIR__.'/composer-toolchain.json'), true, flags: JSON_THROW_ON_ERROR);

if (! is_array($tools) || ! array_is_list($tools) || $tools === []) {
    fwrite(STDERR, "Invalid reviewed Composer toolchain inventory\n");
    exit(2);
}

$versions = [];
foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
    if (! is_array($package) || ! is_string($package['name'] ?? null) || ! is_string($package['version'] ?? null)) {
        fwrite(STDERR, "Invalid Composer lockfile package identity\n");
        exit(2);
    }
    $versions[$package['name']] = $package['version'];
}

$command = ['composer', 'update', '--prefer-dist', '--no-interaction', '--no-progress'];

foreach ($tools as $tool) {
    if (! is_string($tool) || $tool === '') {
        fwrite(STDERR, "Invalid reviewed Composer toolchain identity\n");
        exit(2);
    }

    if (! isset($versions[$tool])) {
        fwrite(STDERR, "CI toolchain missing from composer.lock: {$tool}\n");
        exit(2);
    }

    $command[] = '--with';
    $command[] = $tool.':'.$versions[$tool];
    echo "CI locked toolchain: {$tool} {$versions[$tool]}\n";
}

$command = array_merge($command, array_slice($argv, 1));
$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__));

if (! is_resource($process)) {
    fwrite(STDERR, "Cannot start Composer matrix resolution\n");
    exit(2);
}

exit(proc_close($process));
