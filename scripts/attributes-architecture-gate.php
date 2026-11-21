#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Tooling\AttributesArchitecture;

use CoreX\CustomFields\Internal\CustomFieldsOptions;
use CoreX\Foundation\Tooling\SurfaceScanner;
use Packman\Config\Exceptions\InvalidConfiguration;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

const FIELD_TABLES = [
    'sys_attribute_backfills',
    'sys_attribute_definitions',
    'sys_attribute_documents',
    'sys_attribute_key_reservations',
    'sys_attribute_schema_operations',
    'sys_attribute_write_tokens',
    'sys_field_schema_heads',
];

const CONCURRENT_INDEX_SQL = '/\b(?:CREATE|DROP)\s+INDEX\s+CONCURRENTLY\b/i';
const INDEX_EXECUTOR = '/src/Schemas/Internal/PostgresDdlExecutor.php';

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
            CustomFieldsOptions::fromArray($config);
        } catch (InvalidConfiguration $error) {
            $violations[] = 'A4 '.$fixture.': '.$error->getMessage();
        }
    } else {
        scanSource($fixture, (string) file_get_contents($fixture), true, $violations);
    }
} else {
    $sources = phpFiles($package.'/src');
    foreach (['Values', 'Schemas', 'Types', 'Query'] as $subsystem) {
        if (phpFiles($package.'/src/'.$subsystem) === []) {
            $violations[] = "A0 missing {$subsystem} subsystem";
        }
    }

    foreach (['Contracts', 'Data', 'Exceptions', 'Internal/Engine', 'Internal/Registry', 'Internal/Schema', 'Internal/Values',
        'Internal/Query', 'Internal/Storage', 'Internal/Integration', 'Internal/Laravel'] as $legacy) {
        if (phpFiles($package.'/src/'.$legacy) !== []) {
            $violations[] = "A0 legacy {$legacy} files remain";
        }
    }

    require $root.'/vendor/autoload.php';
    $surface = (new SurfaceScanner)->scan($root);
    $symbols = $surface->manifests['axiomasoft/corex-custom-fields']['symbols'] ?? [];
    foreach ([
        'Values\\Contracts\\FieldValues' => 'spi-call',
        'Schemas\\Contracts\\FieldCatalogue' => 'spi-call',
        'Schemas\\Contracts\\FieldSchemas' => 'spi-call',
        'Query\\Contracts\\FieldFilter' => 'spi-call',
        'Schemas\\Contracts\\DeclaredFields' => 'spi-implement',
        'Types\\Contracts\\FieldCodec' => 'spi-implement',
        'Types\\Contracts\\FieldType' => 'spi-implement',
        'Types\\Contracts\\FieldConverterProvider' => 'spi-implement',
    ] as $port => $layer) {
        $symbol = $symbols['CoreX\\CustomFields\\'.$port] ?? [];

        if (($symbol['layer'] ?? null) !== $layer || ($symbol['kind'] ?? null) !== 'interface') {
            $violations[] = "A0 missing {$layer} interface {$port}";
        }
    }

    $config = require $package.'/config/corex-custom-fields.php';

    if (! is_array($config)) {
        $violations[] = 'A4 shipped config must return an array';
    } else {
        try {
            CustomFieldsOptions::fromArray($config);
        } catch (InvalidConfiguration $error) {
            $violations[] = 'A4 shipped config: '.$error->getMessage();
        }
    }

    foreach ($sources as $file) {
        $code = (string) file_get_contents($file);
        scanSource($file, $code, false, $violations);
        $relative = substr($file, strlen($package.'/src/'), -4);
        $expected = 'CoreX\\CustomFields\\'.str_replace('/', '\\', $relative);
        $expected = substr($expected, 0, (int) strrpos($expected, '\\'));

        if (preg_match('/^namespace\s+([^;]+);/m', $code, $match) !== 1 || $match[1] !== $expected) {
            $violations[] = "A0 namespace/path mismatch: {$file}";
        }
    }
    $executor = $package.INDEX_EXECUTOR;

    if (! is_file($executor) || preg_match(CONCURRENT_INDEX_SQL, (string) file_get_contents($executor)) !== 1) {
        $violations[] = 'A6 missing concurrent index executor';
    }

    $tables = [];
    foreach (phpFiles($package.'/database/migrations') as $file) {
        $code = (string) file_get_contents($file);
        scanSource($file, $code, true, $violations);
        preg_match_all('/(?:\bSchema::|\$this->)create\s*\(\s*[\'\"]([a-z_][a-z0-9_]*)[\'\"]/', $code, $created);
        array_push($tables, ...$created[1]);
    }
    sort($tables);

    if ($tables !== FIELD_TABLES) {
        $violations[] = 'A3 unexpected clean-install tables: '.implode(', ', $tables);
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

    if (preg_match(CONCURRENT_INDEX_SQL, $code) === 1 && $file !== dirname(__DIR__).'/packages/custom-fields'.INDEX_EXECUTOR) {
        $violations[] = "A6 concurrent index DDL outside the owner executor in {$file}";
    }

    if (! $ddl) {
        return;
    }

    preg_match_all('/(?:\bSchema::|\$this->)(?:create|table|drop|dropIfExists|rename)\s*\(/', $code, $calls);
    preg_match_all('/(?:\bSchema::|\$this->)(?:create|table|drop|dropIfExists|rename)\s*\(\s*[\'\"]([^\'\"]+)[\'\"]/', $code, $targets);

    if (count($calls[0]) !== count($targets[1])) {
        $violations[] = "A3 dynamic DDL target in {$file}";
    }

    foreach ($targets[1] as $table) {
        if (! in_array($table, FIELD_TABLES, true)) {
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
