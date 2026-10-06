#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Tooling\AttributesArchitecture;

use CoreX\Attributes\Tooling\SurfaceScanner;
use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\CustomFields\Internal\Laravel\AttributeComposition;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

// The whole-monorepo AST/ownership graph exceeds PHP's 128 MiB CLI default.
ini_set('memory_limit', '1G');
$root = dirname(__DIR__);
$package = $root.'/packages/custom-fields';
$fixture = ($argv[1] ?? null) === '--fixture' ? ($argv[2] ?? null) : null;
$violations = [];

if (($argv[1] ?? null) === '--fixture' && (! is_string($fixture) || ! is_file($fixture))) {
    fwrite(STDERR, "attributes-architecture-gate: fixture file required\n");
    exit(2);
}

if ($fixture !== null) {
    if (str_ends_with($fixture, '.json')) {
        require $root.'/vendor/autoload.php';
        $config = json_decode((string) file_get_contents($fixture), true);

        if (! is_array($config)) {
            fwrite(STDERR, "attributes-architecture-gate: invalid fixture JSON\n");
            exit(2);
        }

        try {
            AttributeComposition::fromArray($config);
        } catch (InvalidConfiguration $error) {
            $violations[] = 'A4 '.$fixture.': '.$error->getMessage();
        }
    } else {
        scanSource($fixture, (string) file_get_contents($fixture), true, $violations);
    }
} else {
    $sources = phpFiles($package.'/src');
    $count = count($sources);

    if ($count < 44 || $count > 100) {
        $violations[] = "A0 Profile B source census must be 44–100 PHP files; found {$count}";
    }

    foreach (['Schema', 'Values', 'Query', 'Storage', 'Integration', 'Laravel'] as $subsystem) {
        if (! is_dir($package.'/src/Internal/'.$subsystem)) {
            $violations[] = "A0 missing {$subsystem} subsystem";
        }
    }

    foreach (['Engine', 'Registry'] as $legacy) {
        if (phpFiles($package.'/src/Internal/'.$legacy) !== []) {
            $violations[] = "A0 legacy Internal/{$legacy} files remain";
        }
    }

    require $root.'/vendor/autoload.php';
    $surface = (new SurfaceScanner)->scan($root);
    $symbols = $surface->manifests['axiomasoft/corex-custom-fields']['symbols'] ?? [];
    foreach (['AttributeStore', 'AttributeFilter', 'FieldCatalogue', 'SchemaOperations', 'AttributeStoreResolver'] as $port) {
        if (! isset($symbols['CoreX\\CustomFields\\Contracts\\'.$port])) {
            $violations[] = 'A0 missing source API/SPI contract '.$port;
        }
    }

    foreach ($sources as $file) {
        scanSource($file, (string) file_get_contents($file), false, $violations);

        $relative = substr($file, strlen($package.'/src/'), -4);
        $subsystem = strtok($relative, '/');

        if (in_array($subsystem, ['Internal', 'Schema', 'Values', 'Query', 'Storage', 'Integration', 'Laravel'], true)) {
            $namespacePath = $relative;
            $expected = 'CoreX\\CustomFields\\'.str_replace('/', '\\', $namespacePath);
            $expected = substr($expected, 0, (int) strrpos($expected, '\\'));

            if (preg_match('/^namespace\s+([^;]+);/m', (string) file_get_contents($file), $match) !== 1 || $match[1] !== $expected) {
                $violations[] = "A0 namespace/path mismatch: {$file}";
            }
        }
    }

    foreach (phpFiles($package.'/database/migrations') as $file) {
        scanSource($file, (string) file_get_contents($file), true, $violations);
    }

    foreach (['package-graph-gate.php', 'config-composition-gate.php', 'table-ownership-gate.php'] as $script) {
        [$status, $output] = runGate($root, $script);

        if ($status !== 0) {
            $violations[] = "A5 {$script} failed (exit {$status}):\n{$output}";
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, implode("\n", $violations)."\n");
    exit(1);
}

fwrite(STDOUT, "attributes-architecture-gate: OK\n");

/** @param list<string> $violations */
function scanSource(string $file, string $code, bool $ddl, array &$violations): void
{
    if (str_contains($code, 'CoreX\\Search\\')) {
        $violations[] = "A1 search import in {$file}";
    }

    if (str_contains($file, 'fixture') && preg_match('/(?<!->)(?<!::)\bconfig\s*\(/', $code) === 1) {
        $violations[] = "A2 runtime config() in {$file}";
    }

    if (! $ddl) {
        return;
    }

    preg_match_all('/\bSchema::(?:create|table|drop|dropIfExists|rename)\s*\(/', $code, $calls);
    preg_match_all('/\bSchema::(?:create|table|drop|dropIfExists|rename)\s*\(\s*[\'\"](sys_attribute_[a-z0-9_]+|[^\'\"]+)[\'\"]/', $code, $targets);

    if (count($calls[0]) !== count($targets[1])) {
        $violations[] = "A3 dynamic DDL target in {$file}";
    }

    foreach ($targets[1] as $table) {
        if (! str_starts_with($table, 'sys_attribute_')) {
            $violations[] = "A3 foreign DDL {$table} in {$file}";
        }
    }
}

/** @return list<string> */
function phpFiles(string $directory): array
{
    if (! is_dir($directory)) {
        return [];
    }

    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
        assert($file instanceof SplFileInfo);

        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);

    return $files;
}

/** @return array{int, string} */
function runGate(string $root, string $script): array
{
    require_once $root.'/vendor/autoload.php';
    // Drain both streams concurrently, including when a gate prints many diagnostics.
    $process = new Process([PHP_BINARY, $root.'/scripts/'.$script], $root);
    $process->run();

    return [$process->getExitCode() ?? 2, trim($process->getOutput().$process->getErrorOutput())];
}
