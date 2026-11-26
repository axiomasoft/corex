#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Blocking configuration-read composition gate.
 *
 * Reports configuration reads outside allowlisted composition paths:
 * C1 `config()`, C2 any config repository access, C3 the removed static
 * the removed static `Config` holder, C4 `env()`.
 * Exit 1 when blocking=true and any finding remains.
 */
const EXIT_OK = 0;
const EXIT_CONFIG = 2;

$repoRoot = dirname(__DIR__);
$configPath = $repoRoot.'/scripts/composition-boundary.json';

$config = loadBoundaryConfig($configPath);
$blocking = $config['blocking'];
$allowGlobs = $config['allow_globs'];
$allowPaths = $config['allow_paths'];
$findings = [];

foreach ((($entries = glob($repoRoot.'/packages/*/src', GLOB_ONLYDIR)) === false ? [] : $entries) as $srcRoot) {
    foreach (walkPhpFiles($srcRoot) as $file) {
        $relativeFile = substr($file, strlen($repoRoot) + 1);

        if (isset($allowPaths[$relativeFile]) || pathMatchesAnyGlob($relativeFile, $allowGlobs)) {
            continue;
        }

        foreach (findConfigReads($file) as $finding) {
            $findings[] = [
                'rule' => $finding['rule'],
                'file' => $relativeFile,
                'line' => $finding['line'],
                'what' => $finding['what'],
            ];
        }
    }
}

usort($findings, static fn (array $a, array $b): int => [$a['rule'], $a['file'], $a['line']] <=> [$b['rule'], $b['file'], $b['line']]);

$counts = ['C1' => 0, 'C2' => 0, 'C3' => 0, 'C4' => 0];

foreach ($findings as $finding) {
    $counts[$finding['rule']]++;
    fwrite(STDOUT, sprintf("%s %s:%d %s\n", $finding['rule'], $finding['file'], $finding['line'], $finding['what']));
}

fwrite(STDOUT, sprintf(
    "config-composition-gate: blocking=%s\n",
    $blocking ? 'true' : 'false',
));
fwrite(STDOUT, sprintf(
    "config-composition-gate: C1 count=%d C2 count=%d C3 count=%d C4 count=%d\n",
    $counts['C1'],
    $counts['C2'],
    $counts['C3'],
    $counts['C4'],
));

if ($findings !== [] && $blocking) {
    exit(1);
}

fwrite(STDOUT, "config-composition-gate: OK\n");

exit(EXIT_OK);

/**
 * @return array{blocking: bool, allow_globs: list<string>, allow_paths: array<string, string>}
 */
function loadBoundaryConfig(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "config-composition-gate: missing {$path}\n");
        exit(EXIT_CONFIG);
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        fwrite(STDERR, "config-composition-gate: invalid JSON in {$path}\n");
        exit(EXIT_CONFIG);
    }

    $allowGlobs = $decoded['allow_globs'] ?? null;

    if (! is_array($allowGlobs)) {
        fwrite(STDERR, "config-composition-gate: invalid allow_globs in {$path}\n");
        exit(EXIT_CONFIG);
    }

    foreach ($allowGlobs as $glob) {
        if (! is_string($glob) || $glob === '') {
            fwrite(STDERR, "config-composition-gate: invalid allow_globs entry in {$path}\n");
            exit(EXIT_CONFIG);
        }
    }

    $allowPaths = $decoded['allow_paths'] ?? [];

    if (! is_array($allowPaths)) {
        fwrite(STDERR, "config-composition-gate: invalid allow_paths in {$path}\n");
        exit(EXIT_CONFIG);
    }

    foreach ($allowPaths as $allowPath => $reason) {
        if (! is_string($allowPath) || $allowPath === '' || ! is_string($reason) || $reason === '') {
            fwrite(STDERR, "config-composition-gate: invalid allow_paths entry in {$path}\n");
            exit(EXIT_CONFIG);
        }
    }

    return [
        'blocking' => (bool) ($decoded['blocking'] ?? false),
        'allow_globs' => array_values($allowGlobs),
        'allow_paths' => $allowPaths,
    ];
}

/**
 * @param  list<string>  $globs
 */
function pathMatchesAnyGlob(string $relativePath, array $globs): bool
{
    foreach (globCandidates($relativePath) as $candidate) {
        foreach ($globs as $glob) {
            if (pathMatchesGlob($candidate, str_replace('\\', '/', $glob))) {
                return true;
            }
        }
    }

    return false;
}

/**
 * §13 globs are package-relative (`*\/src/...`). §12 places the validate
 * command at `src/Support/Console`, which is the composition Console root.
 *
 * @return list<string>
 */
function globCandidates(string $path): array
{
    $normalized = str_replace('\\', '/', $path);
    $candidates = [$normalized];

    if (str_starts_with($normalized, 'packages/')) {
        $candidates[] = substr($normalized, strlen('packages/'));
    }

    $aliased = [];

    foreach ($candidates as $candidate) {
        $console = str_replace('/src/Support/Console/', '/src/Console/', $candidate);

        if ($console !== $candidate) {
            $aliased[] = $console;
        }
    }

    return array_values(array_unique([...$candidates, ...$aliased]));
}

function pathMatchesGlob(string $path, string $glob): bool
{
    return preg_match(globToRegex($glob), $path) === 1;
}

function globToRegex(string $glob): string
{
    $segments = explode('/', str_replace('\\', '/', $glob));
    $rendered = [];
    $last = count($segments) - 1;

    foreach ($segments as $index => $segment) {
        if ($segment === '**') {
            $rendered[] = '(?:[^/]+/)*';

            continue;
        }

        $piece = str_replace(['\*', '\?'], ['[^/]*', '[^/]'], preg_quote($segment, '#'));

        if ($index !== $last) {
            $piece .= '/';
        }

        $rendered[] = $piece;
    }

    return '#^'.implode('', $rendered).'$#';
}

/**
 * @return list<array{rule: string, line: int, what: string}>
 */
function findConfigReads(string $file): array
{
    $code = file_get_contents($file);

    if ($code === false) {
        return [];
    }

    $tokens = token_get_all($code, TOKEN_PARSE);
    $findings = [];
    $count = count($tokens);
    $skipped = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    $repositoryTypes = ['illuminate\\contracts\\config\\repository', 'illuminate\\config\\repository', 'illuminate\\support\\facades\\config'];
    $staticConfig = 'corex\\config\\internal\\config';
    $resolvers = ['app', 'make', 'resolve', 'get', 'makewith'];

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token)) {
            continue;
        }

        if (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            $name = strtolower(ltrim($token[1], '\\'));

            if (in_array($name, $repositoryTypes, true)) {
                $findings[] = ['rule' => 'C2', 'line' => $token[2], 'what' => 'config-repository'];
            } elseif ($name === $staticConfig) {
                $findings[] = ['rule' => 'C3', 'line' => $token[2], 'what' => 'static-Config'];
            } elseif ($token[0] === T_NAME_FULLY_QUALIFIED && in_array($name, ['config', 'env'], true) && isFunctionCall($tokens, $i, $count, false)) {
                $findings[] = $name === 'config'
                    ? ['rule' => 'C1', 'line' => $token[2], 'what' => 'config()']
                    : ['rule' => 'C4', 'line' => $token[2], 'what' => 'env()'];
            }

            continue;
        }

        if ($token[0] === T_CONSTANT_ENCAPSED_STRING && in_array($token[1], ["'config'", '"config"'], true)) {
            $before = $i - 1;

            while ($before >= 0 && is_array($tokens[$before]) && in_array($tokens[$before][0], $skipped, true)) {
                $before--;
            }

            if ($before < 0) {
                continue;
            }

            if ($tokens[$before] === '[') {
                $findings[] = ['rule' => 'C2', 'line' => $token[2], 'what' => 'config-repository'];

                continue;
            }

            if ($tokens[$before] === '(') {
                $callee = $before - 1;

                while ($callee >= 0 && is_array($tokens[$callee]) && in_array($tokens[$callee][0], $skipped, true)) {
                    $callee--;
                }

                if ($callee >= 0 && is_array($tokens[$callee]) && $tokens[$callee][0] === T_STRING
                    && in_array(strtolower($tokens[$callee][1]), $resolvers, true)) {
                    $findings[] = ['rule' => 'C2', 'line' => $token[2], 'what' => 'config-repository'];
                }
            }

            continue;
        }

        $name = $token[0] === T_STRING ? strtolower($token[1]) : '';
        $rule = match ($name) {
            'config' => 'C1',
            'env' => 'C4',
            default => null,
        };

        if ($rule === null || ! isFunctionCall($tokens, $i, $count, true)) {
            continue;
        }

        $findings[] = ['rule' => $rule, 'line' => $token[2], 'what' => $rule === 'C1' ? 'config()' : 'env()'];
    }

    return $findings;
}

/**
 * @param  array<int, mixed>  $tokens
 */
function isFunctionCall(array $tokens, int $i, int $count, bool $plain): bool
{
    $skipped = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];
    $previous = $i - 1;

    while ($previous >= 0 && is_array($tokens[$previous]) && in_array($tokens[$previous][0], $skipped, true)) {
        $previous--;
    }

    if ($plain && $previous >= 0 && is_array($tokens[$previous])
        && in_array($tokens[$previous][0], [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
        return false;
    }

    $next = $i + 1;

    while ($next < $count && is_array($tokens[$next]) && in_array($tokens[$next][0], $skipped, true)) {
        $next++;
    }

    return $next < $count && $tokens[$next] === '(';
}

/**
 * @return list<string>
 */
function walkPhpFiles(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $current): bool => ! $current->isDir()
                || ! in_array($current->getFilename(), ['vendor', 'node_modules'], true),
        ),
    );

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}
