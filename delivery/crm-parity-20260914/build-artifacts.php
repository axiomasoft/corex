<?php

declare(strict_types=1);

// Generates local Composer artifact archives from immutable Git bytes. No push/tag.
$ref = $argv[1] ?? throw new RuntimeException('Pass a full immutable source commit.');

if (preg_match('/^[a-f0-9]{40}$/D', $ref) !== 1) {
    throw new RuntimeException('Expected a full Git commit SHA.');
}
$root = dirname(__DIR__, 2);
$output = __DIR__.'/artifacts';

if (! is_dir($output)) {
    mkdir($output, 0700, true);
}
function gitBytes(array $arguments): string
{
    $process = proc_open(['git', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
    $value = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        throw new RuntimeException($error);
    }

    return $value;
}
$selector = 'dev-crm-parity-'.substr($ref, 0, 12);
foreach (['core', 'modules', 'crm'] as $package) {
    $prefix = 'packages/'.$package.'/';
    $manifest = json_decode(gitBytes(['show', $ref.':'.$prefix.'composer.json']), true, flags: JSON_THROW_ON_ERROR);
    $manifest['version'] = $selector;
    foreach (['corex/core', 'corex/modules'] as $dependency) {
        if (isset($manifest['require'][$dependency])) {
            $manifest['require'][$dependency] = $selector;
        }
    }
    unset($manifest['repositories'], $manifest['require-dev'], $manifest['autoload-dev']);
    $manifest['extra']['corex-source'] = ['commit' => $ref, 'subtree' => trim(gitBytes(['rev-parse', $ref.':packages/'.$package]))];
    $zip = new ZipArchive;
    $name = $output.'/'.str_replace('/', '-', $manifest['name']).'-'.substr($ref, 0, 12).'.zip';

    if (file_exists($name)) {
        throw new RuntimeException('Refuse overwriting an existing immutable archive: '.$name);
    }

    if ($zip->open($name, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Cannot create artifact '.$name);
    }
    $files = array_filter(explode("\n", gitBytes(['ls-tree', '-r', '--name-only', $ref.':packages/'.$package])));
    foreach ($files as $file) {
        if ($file === 'composer.json' || str_starts_with($file, 'tests/') || in_array($file, ['phpunit.xml', 'phpstan.neon'], true)) {
            continue;
        }
        $zip->addFromString($file, gitBytes(['show', $ref.':'.$prefix.$file]));
    }
    $zip->addFromString('composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    $zip->close();
    echo $manifest['name'].' '.$selector.' '.hash_file('sha256', $name).' '.$name.PHP_EOL;
}
