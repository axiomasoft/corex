#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Tooling\Quality;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Throwable;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

/** @return array<string, mixed> */
function document(string $path): array
{
    $text = file_get_contents($path);

    if ($text === false) {
        throw new RuntimeException("Unreadable config: {$path}");
    }
    $data = str_ends_with($path, '.json')
        ? json_decode($text, true, flags: JSON_THROW_ON_ERROR)
        : Yaml::parse(str_replace("\t", '    ', $text));

    if (! is_array($data)) {
        throw new RuntimeException("Invalid config: {$path}");
    }
    foreach (array_keys($data) as $key) {
        if (! is_string($key)) {
            throw new RuntimeException("Invalid config key: {$path}");
        }
    }

    $result = [];
    foreach ($data as $key => $value) {
        $result[(string) $key] = $value;
    }

    return $result;
}

/** @return list<string> */
function strings(mixed $values, string $label): array
{
    if (! is_array($values) || ! array_is_list($values)) {
        throw new RuntimeException("Invalid list: {$label}");
    }
    foreach ($values as $value) {
        if (! is_string($value)) {
            throw new RuntimeException("Invalid string: {$label}");
        }
    }

    return $values;
}

/** @return list<string> */
function files(string $path): array
{
    if (is_file($path)) {
        return [$path];
    }

    if (! is_dir($path)) {
        throw new RuntimeException("Missing analysis path: {$path}");
    }
    $result = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $result[] = $file->getPathname();
        }
    }

    return $result;
}

function disablesRule(mixed $value): bool
{
    return $value === false || (is_array($value) && array_any($value, disablesRule(...)));
}

/** @return list<string> */
function check(string $root, string $stage): array
{
    $cohort = ['root' => '.', 'core' => 'packages/core', 'access-azguard' => 'packages/access-azguard'];

    if ($stage !== 'foundation') {
        foreach (['access', 'tenancy', 'operations', 'tools', 'webhooks'] as $package) {
            $cohort[$package] = 'packages/'.$package;
        }
    }

    if ($stage === 'all') {
        foreach (array_merge((($entries = glob($root.'/packages/*/composer.json')) === false ? [] : $entries), (($entries = glob($root.'/products/*/composer.json')) === false ? [] : $entries)) as $manifest) {
            $dir = dirname($manifest);
            $cohort[basename($dir)] = substr($dir, strlen($root) + 1);
        }
    }
    $ceilings = document($root.'/scripts/quality-ceilings.json');
    $phpstan = $ceilings['phpstan'] ?? null;

    if (! is_array($phpstan)) {
        throw new RuntimeException('Missing PHPStan ceilings');
    }
    $schema = document($root.'/scripts/schema-sets.json');
    $sets = $schema['sets'] ?? null;

    if (! is_array($sets)) {
        throw new RuntimeException('Missing SchemaSet inventory');
    }
    $extensions = document($root.'/phpstan-extensions.neon');
    $extensionIncludes = strings($extensions['includes'] ?? null, 'extensions.includes');

    if (! in_array('vendor/phpstan/phpstan-strict-rules/rules.neon', $extensionIncludes, true)) {
        throw new RuntimeException('Missing strict rules');
    }

    $extensionParameters = $extensions['parameters'] ?? [];

    if (! is_array($extensionParameters)) {
        throw new RuntimeException('Invalid shared rule parameters');
    }

    if (disablesRule($extensionParameters['strictRules'] ?? [])
        || disablesRule($extensionParameters['featureToggles'] ?? [])) {
        throw new RuntimeException('Disabled shared strict/bleedingEdge rules');
    }
    $violations = [];
    foreach ($cohort as $key => $dir) {
        $base = $root.'/'.$dir;
        $config = document($base.'/phpstan.neon');
        $includes = strings($config['includes'] ?? null, $key.'.includes');
        $parameters = $config['parameters'] ?? null;

        if (! is_array($parameters)) {
            throw new RuntimeException("Missing parameters: {$key}");
        }

        if (($parameters['level'] ?? null) !== 'max'
            || ! in_array('phar://phpstan.phar/conf/bleedingEdge.neon', $includes, true)
            || ! in_array($key === 'root' ? 'phpstan-extensions.neon' : '../../phpstan-extensions.neon', $includes, true)) {
            $violations[] = "{$key}: max/strict/bleedingEdge required";
        }

        if (($phpstan[$key] ?? null) !== 0) {
            $violations[] = "{$key}: ceiling must be zero";
        }

        if (disablesRule($parameters['strictRules'] ?? []) || disablesRule($parameters['featureToggles'] ?? [])) {
            $violations[] = "{$key}: disabled strict/bleedingEdge rules";
        }
        foreach ($parameters as $name => $value) {
            if (in_array($name, ['ignoreErrors', 'excludePaths', 'stubFiles'], true) && $value !== []) {
                $violations[] = "{$key}: forbidden {$name}";
            }

            if (in_array($name, ['reportUnmatchedIgnoredErrors', 'treatPhpDocTypesAsCertain', 'checkModelProperties', 'checkPhpDocMissingReturn'], true) && $value === false) {
                $violations[] = "{$key}: disabled {$name}";
            }
        }
        foreach ($includes as $include) {
            if (str_starts_with($include, 'phar://') || str_ends_with($include, 'phpstan-extensions.neon')) {
                continue;
            }
            $ignored = document($base.'/'.$include);

            if ($ignored !== ['parameters' => ['ignoreErrors' => []]]) {
                $violations[] = "{$key}: nonempty baseline or undeclared include {$include}";
            }
        }
        $paths = strings($parameters['paths'] ?? null, $key.'.paths');
        $hasMigrations = is_dir($base.'/database/migrations')
            || array_any($sets, static fn (mixed $set): bool => is_array($set)
                && is_string($set['path'] ?? null)
                && str_starts_with($set['path'], $dir.'/database/migrations/'));
        $required = $key === 'root' ? ['tests/Support', 'tests/Golden', 'scripts'] : ['src'];

        if ($key !== 'root' && $hasMigrations) {
            $required[] = 'database/migrations';
        }
        foreach ($required as $path) {
            if (! in_array($path, $paths, true)) {
                $violations[] = "{$key}: missing analysis path {$path}";
            }
        }
        foreach ($sets as $set) {
            if (! is_array($set) || ! is_string($set['path'] ?? null)) {
                throw new RuntimeException('Invalid SchemaSet path');
            }

            if ($key !== 'root' && str_starts_with($set['path'], $dir.'/')) {
                $relative = substr($set['path'], strlen($dir) + 1);

                if (! is_dir($root.'/'.$set['path']) || ! array_any($paths, static fn (string $path): bool => $relative === $path || str_starts_with($relative, rtrim($path, '/').'/'))) {
                    $violations[] = "{$key}: uncovered SchemaSet {$relative}";
                }
            }
        }
        foreach ($paths as $path) {
            foreach (files($base.'/'.$path) as $file) {
                $text = file_get_contents($file);

                if ($text === false) {
                    throw new RuntimeException("Unreadable source: {$file}");
                }
                foreach (token_get_all($text) as $token) {
                    if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)
                        && preg_match('/@phpstan-ignore\b/', $token[1]) === 1) {
                        $violations[] = substr($file, strlen($root) + 1).':'.$token[2].': inline suppression';
                    }
                }
            }
        }
    }

    return $violations;
}

$options = getopt('', ['stage:', 'root:']);
$stage = $options['stage'] ?? null;
$root = $options['root'] ?? dirname(__DIR__, 2);

try {
    if (! is_string($stage) || ! in_array($stage, ['foundation', 'security', 'all'], true) || ! is_string($root)) {
        throw new RuntimeException('Usage: release-quality.php --stage=foundation|security|all [--root=<fixture>]');
    }
    $errors = check(rtrim($root, '/'), $stage);

    if ($errors !== []) {
        throw new RuntimeException(implode("\n", $errors));
    }
    echo "quality {$stage} OK\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'quality FAIL: '.$error->getMessage()."\n");
    exit(1);
}
