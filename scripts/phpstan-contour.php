#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * PHPStan contour for live packages (PLAN2 P3.1 · N03).
 *
 * Measures package-level `composer analyse`, writes phpstan-contour.json,
 * syncs root phpstan.neon paths to currently-green packages only.
 */
const EXIT_OK = 0;
const EXIT_RUNTIME = 1;

$repoRoot = dirname(__DIR__);
$planId = '2026.09.19-№1-COREX-PLATFORM-HARDENING';
$artifactPath = $repoRoot.'/plans/'.$planId.'/artifacts/architecture/phpstan-contour.json';
$neonPath = $repoRoot.'/phpstan.neon';

$alsoAnalyseRoot = in_array('--also-analyse-root', $argv, true);

$dirs = testPackageDirs($repoRoot);
$rootLevel = rootPhpStanLevel($neonPath);
$currentPaths = readNeonPaths($neonPath);

$packages = [];
$debt = [];

foreach ($dirs as $dir) {
    $composerPath = $repoRoot.'/packages/'.$dir.'/composer.json';

    if (! is_file($composerPath)) {
        fwrite(STDERR, "missing composer.json for package dir: {$dir}\n");
        exit(EXIT_RUNTIME);
    }

    $composer = json_decode((string) file_get_contents($composerPath), true);

    if (! is_array($composer)) {
        fwrite(STDERR, "invalid composer.json: {$composerPath}\n");
        exit(EXIT_RUNTIME);
    }

    $name = (string) ($composer['name'] ?? '');

    if ($name === '') {
        fwrite(STDERR, "missing name in {$composerPath}\n");
        exit(EXIT_RUNTIME);
    }

    $srcPath = 'packages/'.$dir.'/src';
    $inRootNeon = in_array($srcPath, $currentPaths, true);

    $exit = runPackageAnalyse($repoRoot, $dir);
    $pass = $exit === 0;

    $packages[] = [
        'name' => $name,
        'dir' => $dir,
        'pass' => $pass,
        'in_root_neon' => $inRootNeon,
        'exit' => $exit,
    ];

    if (! $pass) {
        $debt[] = [
            'name' => $name,
            'dir' => $dir,
            'exit' => $exit,
        ];
    }
}

usort($packages, static fn (array $a, array $b): int => strcmp($a['dir'], $b['dir']));
usort($debt, static fn (array $a, array $b): int => strcmp($a['dir'], $b['dir']));

$payload = [
    'schema' => 'phpstan-contour/1',
    'root_level' => $rootLevel,
    'packages' => $packages,
    'debt' => $debt,
];

writeJson($artifactPath, $payload);
syncRootNeon($neonPath, $repoRoot, $packages, $currentPaths);

if ($alsoAnalyseRoot) {
    $exit = runProc($repoRoot, ['composer', 'analyse'], $repoRoot);
    exit($exit === 0 ? EXIT_OK : EXIT_RUNTIME);
}

exit(EXIT_OK);

/** @return list<string> */
function testPackageDirs(string $repoRoot): array
{
    $composer = json_decode((string) file_get_contents($repoRoot.'/composer.json'), true);
    $script = (string) ($composer['scripts']['test:packages'] ?? '');

    if (! preg_match('/for p in (.+?); do/s', $script, $matches)) {
        fwrite(STDERR, "cannot parse test:packages script\n");
        exit(EXIT_RUNTIME);
    }

    $dirs = array_values(array_filter(explode(' ', trim($matches[1]))));

    if (count($dirs) !== 20) {
        fwrite(STDERR, 'expected 20 live packages, got '.count($dirs)."\n");
        exit(EXIT_RUNTIME);
    }

    return $dirs;
}

function rootPhpStanLevel(string $neonPath): int
{
    $contents = (string) file_get_contents($neonPath);

    if (preg_match('/^\s*level:\s*(\d+)\s*$/m', $contents, $matches)) {
        return (int) $matches[1];
    }

    fwrite(STDERR, "cannot read level from {$neonPath}\n");
    exit(EXIT_RUNTIME);
}

/** @return list<string> */
function readNeonPaths(string $neonPath): array
{
    $contents = (string) file_get_contents($neonPath);

    if (! preg_match('/^\s*paths:\s*\n((?:\s+-\s+.+\n)+)/m', $contents, $matches)) {
        fwrite(STDERR, "cannot parse paths from {$neonPath}\n");
        exit(EXIT_RUNTIME);
    }

    preg_match_all('/^\s+-\s+(.+)\s*$/m', $matches[1], $pathMatches);

    return array_values($pathMatches[1]);
}

function runPackageAnalyse(string $repoRoot, string $dir): int
{
    return runProc(
        $repoRoot,
        ['composer', '--working-dir=packages/'.$dir, 'analyse'],
        $repoRoot,
    );
}

/**
 * @param  list<string>  $command
 */
function runProc(string $cwd, array $command): int
{
    $escaped = array_map('escapeshellarg', $command);
    $line = implode(' ', $escaped);

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['file', 'php://stderr', 'w'],
        2 => ['file', 'php://stderr', 'w'],
    ];

    $process = proc_open($line, $descriptorSpec, $pipes, $cwd);

    if (! is_resource($process)) {
        fwrite(STDERR, "failed to start: {$line}\n");
        exit(EXIT_RUNTIME);
    }

    fclose($pipes[0]);

    return proc_close($process);
}

/**
 * @param  array<string, mixed>  $payload
 */
function writeJson(string $path, array $payload): void
{
    $dir = dirname($path);

    if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
        fwrite(STDERR, "cannot create directory: {$dir}\n");
        exit(EXIT_RUNTIME);
    }

    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        fwrite(STDERR, "json encode failed\n");
        exit(EXIT_RUNTIME);
    }

    $json .= "\n";

    if (file_put_contents($path, $json) === false) {
        fwrite(STDERR, "cannot write {$path}\n");
        exit(EXIT_RUNTIME);
    }
}

/**
 * @param  list<array{name: string, dir: string, pass: bool, in_root_neon: bool, exit: int}>  $packages
 * @param  list<string>  $previousPaths
 */
function syncRootNeon(string $neonPath, string $repoRoot, array $packages, array $previousPaths): void
{
    $paths = [];

    foreach ($packages as $row) {
        if (! $row['pass']) {
            continue;
        }

        $dir = $row['dir'];
        $paths[] = 'packages/'.$dir.'/src';

        $migrations = 'packages/'.$dir.'/database/migrations';

        if (! is_dir($repoRoot.'/'.$migrations)) {
            continue;
        }

        if (in_array($migrations, $previousPaths, true) || analyseIncludesMigrations($repoRoot, $dir)) {
            $paths[] = $migrations;
        }
    }

    $paths = array_values(array_unique($paths));
    sort($paths);

    $contents = (string) file_get_contents($neonPath);
    $replacement = "    paths:\n";
    foreach ($paths as $path) {
        $replacement .= '        - '.$path."\n";
    }

    $updated = preg_replace(
        '/^\s*paths:\s*\n(?:\s+-\s+.+\n)+/m',
        $replacement,
        $contents,
        1,
        $count,
    );

    if ($count !== 1 || $updated === null) {
        fwrite(STDERR, "failed to rewrite paths in {$neonPath}\n");
        exit(EXIT_RUNTIME);
    }

    if (file_put_contents($neonPath, $updated) === false) {
        fwrite(STDERR, "cannot write {$neonPath}\n");
        exit(EXIT_RUNTIME);
    }
}

function analyseIncludesMigrations(string $repoRoot, string $dir): bool
{
    $composer = json_decode(
        (string) file_get_contents($repoRoot.'/packages/'.$dir.'/composer.json'),
        true,
    );
    $script = (string) ($composer['scripts']['analyse'] ?? '');

    return str_contains($script, 'database/migrations');
}
