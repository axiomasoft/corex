<?php

declare(strict_types=1);

use CoreX\Attributes\Api;
use CoreX\Attributes\Spi;
use CoreX\Modules\Data\InstalledPackageVersion;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Testing\ExtensionContract;
use CoreX\Search\Internal\SearchManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

$arguments = parseArguments(array_slice($argv, 1));
$vendor = $arguments['vendor'];
$fixture = $arguments['fixture'];
$class = $arguments['class'];
$package = $arguments['package'];
$forbidden = $arguments['forbidden'];
$workspace = $arguments['workspace'];

require $vendor.'/autoload.php';

$allowed = allowedSymbols($vendor);
$files = fixtureFiles($fixture);
$clean = scanFiles($files, $allowed);

if ($clean !== []) {
    fail('public-import', $clean);
}

$manifest = $class::manifest();
$version = $class::installedVersion();
assert($manifest instanceof ModuleManifest);
assert($version instanceof InstalledPackageVersion);
$report = ExtensionContract::verify(
    manifests: [$manifest],
    installedPackages: [$version->name => $version],
);

if (! $report->passed() || ! is_string($report->fingerprint) || preg_match('/\A[a-f0-9]{64}\z/', $report->fingerprint) !== 1) {
    fail('extension-contract', [$report->summary()]);
}

if (method_exists($class, 'driver')) {
    $driver = $class::driver();
    $container = new Container;
    $container->instance('config', new Repository([
        'corex-search' => ['driver' => 'thirdparty'],
    ]));
    $manager = new SearchManager($container);
    $manager->extend('thirdparty', fn (mixed ...$ignored) => $driver);
    $class::bind($manager->driver('thirdparty'));
}

$class::prove();
$class::prove();

$planted = plant($files[0], $package, $forbidden, $allowed);
$restored = scanFiles($files, $allowed);

if ($restored !== []) {
    fail('restore', $restored);
}

echo json_encode([
    'package' => $package,
    'contract' => 'pass',
    'fingerprint' => $report->fingerprint,
    'behavior_orders' => ['original', 'reversed'],
    'internal_import' => $planted,
    'autoload_workspace' => workspaceLeak($vendor, $workspace),
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";

/** @param  list<string>  $argv
 * @return array{vendor: string, fixture: string, class: string, package: string, forbidden: string, workspace: string}
 */
function parseArguments(array $argv): array
{
    $values = [];

    for ($index = 0; $index < count($argv); $index += 2) {
        $key = $argv[$index];
        $values[ltrim($key, '-')] = $argv[$index + 1] ?? '';
    }

    foreach (['vendor', 'fixture', 'class', 'package', 'forbidden', 'workspace'] as $key) {
        if (($values[$key] ?? '') === '') {
            fail('arguments', ["missing {$key}"]);
        }
    }

    return ['vendor' => $values['vendor'], 'fixture' => $values['fixture'], 'class' => $values['class'], 'package' => $values['package'], 'forbidden' => $values['forbidden'], 'workspace' => $values['workspace']];
}

/** @return array<string, true> */
function allowedSymbols(string $vendor): array
{
    $installed = json_decode((string) file_get_contents($vendor.'/composer/installed.json'), true, flags: JSON_THROW_ON_ERROR);
    assert(is_array($installed));
    $packages = $installed['packages'] ?? $installed;
    assert(is_array($packages));
    $allowed = [];
    $queue = [];

    foreach ($packages as $package) {
        assert(is_array($package));
        $extra = $package['extra'] ?? [];
        assert(is_array($extra));

        if (! isset($extra['corex'])) {
            continue;
        }
        $install = $package['install-path'] ?? '';
        assert(is_string($install));
        $root = $vendor.'/composer/'.$install;
        foreach (phpFiles($root) as $file) {
            foreach (declaredSymbols($file) as $symbol) {
                if (! (class_exists($symbol) || interface_exists($symbol) || trait_exists($symbol) || enum_exists($symbol))) {
                    continue;
                }
                $reflection = new ReflectionClass($symbol);

                if ($reflection->getAttributes(Api::class) !== []
                    || $reflection->getAttributes(Spi::class) !== []) {
                    $queue[] = $symbol;
                }
            }
        }
    }

    while ($queue !== []) {
        $symbol = array_pop($queue);

        if (isset($allowed[$symbol]) || str_contains($symbol, '\\Internal\\')) {
            continue;
        }

        try {
            $exists = class_exists($symbol) || interface_exists($symbol) || enum_exists($symbol);
        } catch (Throwable) {
            continue;
        }

        if (! $exists) {
            continue;
        }

        $allowed[$symbol] = true;
        $reflection = enum_exists($symbol)
            ? new ReflectionEnum($symbol)
            : new ReflectionClass($symbol);
        $uses = importedSymbols((string) $reflection->getFileName());
        $namespace = $reflection->getNamespaceName();

        foreach (reflectionTypes($reflection) as $type) {
            enqueue($queue, $allowed, resolveSymbol($type, $namespace, $uses));
        }
    }

    return $allowed;
}

/** @param  list<string>  $queue
 * @param  array<string, true>  $allowed
 */
function enqueue(array &$queue, array $allowed, string $symbol): void
{
    if ($symbol !== '' && ! isset($allowed[$symbol]) && ! str_contains($symbol, '\\Internal\\') && str_starts_with($symbol, 'CoreX\\')) {
        $queue[] = $symbol;
    }
}

/**
 * @template T of object
 *
 * @param  ReflectionClass<T>|ReflectionEnum<UnitEnum>  $reflection
 * @return list<string>
 */
function reflectionTypes(ReflectionClass $reflection): array
{
    $types = [];
    $docblocks = [(string) $reflection->getDocComment()];

    foreach ($reflection->getMethods() as $method) {
        $docblocks[] = (string) $method->getDocComment();

        if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
            continue;
        }

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionType) {
                array_push($types, ...namedTypes($type));
            }
        }

        $return = $method->getReturnType();

        if ($return instanceof ReflectionType) {
            array_push($types, ...namedTypes($return));
        }
    }

    foreach ($reflection->getProperties() as $property) {
        $docblocks[] = (string) $property->getDocComment();
        $type = $property->getType();

        if ($type instanceof ReflectionType) {
            array_push($types, ...namedTypes($type));
        }
    }

    $uses = importedSymbols((string) $reflection->getFileName());

    foreach ($docblocks as $docblock) {
        if (preg_match_all('/[A-Z][A-Za-z0-9_\\\\]*/', $docblock, $matches) < 1) {
            continue;
        }

        foreach ($matches[0] as $token) {
            $types[] = resolveSymbol($token, $reflection->getNamespaceName(), $uses);
        }
    }

    return $types;
}

/** @return list<string> */
function namedTypes(ReflectionType $type): array
{
    if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
        $names = [];

        foreach ($type->getTypes() as $member) {
            array_push($names, ...namedTypes($member));
        }

        return $names;
    }

    if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
        return [$type->getName()];
    }

    return [];
}

/** @param  array<string, string>  $uses */
function resolveSymbol(string $token, string $namespace, array $uses): string
{
    $token = ltrim($token, '\\');

    if ($token === '' || str_contains($token, '\\') && str_starts_with($token, 'CoreX\\')) {
        return $token;
    }

    if (isset($uses[$token])) {
        return $uses[$token];
    }

    $prefix = strstr($token, '\\', true);
    $head = $prefix === false || $prefix === '' || $prefix === '0' ? $token : $prefix;

    if (isset($uses[$head])) {
        return $uses[$head].substr($token, strlen($head));
    }

    return $namespace === '' ? $token : $namespace.'\\'.$token;
}

/** @return array<string, string> */
function importedSymbols(string $file): array
{
    $uses = [];

    if ($file === '' || ! is_file($file)) {
        return $uses;
    }

    $code = (string) file_get_contents($file);

    if (preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)(?:\s+as\s+([A-Za-z0-9_]+))?;/m', $code, $matches, PREG_SET_ORDER) < 1) {
        return $uses;
    }

    foreach ($matches as $match) {
        $symbol = $match[1];
        $leaf = strrchr('\\'.$symbol, '\\');
        assert(is_string($leaf));
        $alias = ($match[2] ?? '') !== '' ? $match[2] : substr($leaf, 1);
        $alias = ltrim($alias, '\\');
        $uses[$alias] = $symbol;
    }

    return $uses;
}

/** @return list<string> */
function declaredSymbols(string $file): array
{
    $code = (string) file_get_contents($file);

    if (preg_match('/^namespace\s+([A-Za-z0-9_\\\\]+);/m', $code, $namespace) !== 1) {
        return [];
    }

    if (preg_match_all('/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+([A-Za-z0-9_]+)/m', $code, $matches) < 1) {
        return [];
    }

    return array_map(static fn (string $name): string => $namespace[1].'\\'.$name, $matches[1]);
}

/** @param  list<string>  $prefixes */
function covered(string $symbol, array $prefixes): bool
{
    foreach ($prefixes as $prefix) {
        if ($symbol === $prefix || str_starts_with($symbol, $prefix.'\\')) {
            return true;
        }
    }

    return false;
}

/** @return list<string> */
function phpFiles(string $root): array
{
    if (! is_dir($root)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file) {
        assert($file instanceof SplFileInfo);

        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/** @return list<string> */
function fixtureFiles(string $fixture): array
{
    $files = phpFiles($fixture.'/src');

    if ($files === []) {
        fail('fixture', ['fixture has no PHP source']);
    }

    return $files;
}

/**
 * @param  list<string>  $files
 * @param  array<string, true>  $allowed
 * @return list<string>
 */
function scanFiles(array $files, array $allowed): array
{
    $violations = [];

    foreach ($files as $file) {
        foreach (referencedCorex($file) as $symbol) {
            if (! isset($allowed[$symbol]) || str_contains($symbol, '\\Internal\\')) {
                $violations[] = $symbol;
            }
        }
    }

    return array_values(array_unique($violations));
}

/** @return list<string> */
function referencedCorex(string $file): array
{
    $code = (string) file_get_contents($file);
    $symbols = [];

    if (preg_match_all('/^use\s+(CoreX\\\\[A-Za-z0-9_\\\\]+)\s*;/m', $code, $matches) > 0) {
        array_push($symbols, ...$matches[1]);
    }

    if (preg_match_all('/(?<![A-Za-z0-9_])CoreX\\\\[A-Za-z0-9_\\\\]+/', $code, $matches) > 0) {
        array_push($symbols, ...$matches[0]);
    }

    return array_values(array_unique($symbols));
}

/** @param  array<string, true>  $allowed */
function plant(string $file, string $package, string $forbidden, array $allowed): string
{
    $original = (string) file_get_contents($file);
    $needle = "declare(strict_types=1);\n";
    $planted = str_replace($needle, $needle."\nuse {$forbidden};\n", $original, $count);

    if ($count !== 1) {
        fail('plant', ['could not insert the internal import']);
    }

    try {
        file_put_contents($file, $planted);
        $violations = scanFiles([$file], $allowed);
        $diagnostic = $package.' imports forbidden '.$forbidden;

        if (! in_array($forbidden, $violations, true) || ! str_contains($diagnostic, $package) || ! str_contains($diagnostic, $forbidden)) {
            fail('internal-import', [$diagnostic, ...$violations]);
        }
    } finally {
        file_put_contents($file, $original);
    }

    return $package.' imports forbidden '.$forbidden;
}

function workspaceLeak(string $vendor, string $workspace): bool
{
    $autoload = (string) file_get_contents($vendor.'/composer/autoload_psr4.php');

    return str_contains($autoload, $workspace);
}

/** @param  list<string>  $details */
function fail(string $check, array $details): never
{
    fwrite(STDERR, json_encode(['package' => $GLOBALS['package'] ?? null, 'check' => $check, 'details' => $details], JSON_UNESCAPED_SLASHES)."\n");
    exit(1);
}
