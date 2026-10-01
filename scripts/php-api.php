#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal;

use RuntimeException;
use Throwable;

// The whole-monorepo AST/ownership graph exceeds PHP's 128 MiB CLI default.
ini_set('memory_limit', '1G');
require dirname(__DIR__).'/vendor/autoload.php';

try {
    $options = [];
    for ($i = 1; $i < count($argv); $i++) {
        $parts = explode('=', $argv[$i], 2);
        $key = substr($parts[0], 2);

        if (! str_starts_with($parts[0], '--') || ! in_array($key, ['root', 'format', 'report', 'strict', 'write', 'check'], true) || isset($options[$key])) {
            throw new RuntimeException('Unknown or duplicate argument: '.$argv[$i]);
        }

        if (in_array($key, ['root', 'format'], true)) {
            $options[$key] = $parts[1] ?? ($argv[++$i] ?? throw new RuntimeException('Missing value for '.$key));
        } else {
            if (isset($parts[1])) {
                throw new RuntimeException('Flag does not accept a value: '.$key);
            }
            $options[$key] = true;
        }
    }

    if (isset($options['write'], $options['check'])) {
        throw new RuntimeException('--write and --check are exclusive');
    }
    $root = $options['root'] ?? dirname(__DIR__);

    if (($options['format'] ?? 'json') !== 'json') {
        throw new RuntimeException('Expected --root=DIR and --format=json');
    }
    $root = realpath($root);

    if ($root === false) {
        throw new RuntimeException('Source root does not exist');
    }
    $report = (new SurfaceScanner)->scan($root);
    $stale = [];

    if (isset($options['write']) || isset($options['check'])) {
        $artifacts = ['docs/reference/public-api.md' => $report->documentation()];
        foreach ($report->manifests as $package => $manifest) {
            $artifacts[$report->packagePaths[$package].'/resources/contracts/php-api.json'] = SurfaceReport::encode($manifest);
        }
        foreach ($artifacts as $relativeFile => $bytes) {
            $file = $root.'/'.$relativeFile;

            if (isset($options['write'])) {
                if (! is_dir(dirname($file)) && ! mkdir(dirname($file), 0777, true)) {
                    throw new RuntimeException('Cannot create output directory');
                }

                if (file_put_contents($file, $bytes) !== strlen($bytes)) {
                    throw new RuntimeException('Cannot write '.$file);
                }
            } elseif (! is_file($file) || file_get_contents($file) !== $bytes) {
                $stale[] = substr($file, strlen($root) + 1);
            }
        }
    }
    echo $report->json();
    foreach ($stale as $file) {
        fwrite(STDERR, "API-STALE {$file}\n");
    }
    exit($stale !== [] || (isset($options['strict']) && $report->diagnostics !== []) ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'API-CONFIG '.$error->getMessage()."\n");
    exit(2);
}
