#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Blocking provider-size gate (P8.6, D45).
 *
 * Counts singleton|scoped|bind|bindIf call sites only inside classes that
 * extend Illuminate\Support\ServiceProvider. A foreach body is one call site.
 * Exit 1 when a provider grows past its ratchet, or when a provider that is
 * not in the snapshot exceeds max_bindings. CoreServiceProvider may stay
 * above that cap: P8 freezes its measured size and still rejects growth.
 * `--report` prints the measured snapshot and does not judge it.
 */
const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

const PROVIDER_PARENT = 'Illuminate\\Support\\ServiceProvider';
const BINDING_METHODS = ['singleton', 'scoped', 'bind', 'bindIf'];
const MAX_BINDINGS = 20;
const CORE_PROVIDER = 'packages/core/src/CoreServiceProvider.php';

$repoRoot = dirname(__DIR__);
$configPath = $repoRoot.'/scripts/provider-size.json';
$reportOnly = in_array('--report', $argv, true);

$measured = measureProviders($repoRoot);

if ($reportOnly) {
    fwrite(STDOUT, json_encode($measured, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    exit(EXIT_OK);
}

$config = loadProviderSizeConfig($configPath);
$violations = judgeProviders($measured, $config['ratchet']);

foreach ($violations as $violation) {
    fwrite(STDOUT, $violation."\n");
}

if ($violations !== []) {
    exit(EXIT_VIOLATION);
}

fwrite(STDOUT, "provider-size-gate: OK\n");
exit(EXIT_OK);

/**
 * @return array<string, array{singleton: int, scoped: int, bind: int, bindIf: int, total: int}>
 */
function measureProviders(string $repoRoot): array
{
    $measured = [];

    foreach (glob($repoRoot.'/packages/*/src', GLOB_ONLYDIR) ?: [] as $srcRoot) {
        foreach (walkPhpFiles($srcRoot) as $file) {
            $counts = countBindingCalls($file);

            if ($counts === null) {
                continue;
            }

            $relative = substr($file, strlen($repoRoot) + 1);
            $measured[str_replace('\\', '/', $relative)] = $counts;
        }
    }

    ksort($measured);

    return $measured;
}

/**
 * @param  array<string, array{singleton: int, scoped: int, bind: int, bindIf: int, total: int}>  $measured
 * @param  array<string, int>  $ratchet
 * @return list<string>
 */
function judgeProviders(array $measured, array $ratchet): array
{
    $violations = [];

    foreach ($measured as $relative => $counts) {
        $reasons = [];

        $frozen = $ratchet[$relative] ?? null;

        if ($frozen === null && $counts['total'] > MAX_BINDINGS) {
            $reasons[] = 'max_bindings='.MAX_BINDINGS;
        }

        if ($frozen !== null && $counts['total'] > $frozen) {
            $reasons[] = 'ratchet='.$frozen;
        }

        if ($frozen !== null && $relative !== CORE_PROVIDER && $counts['total'] > MAX_BINDINGS) {
            $reasons[] = 'max_bindings='.MAX_BINDINGS;
        }

        if ($reasons === []) {
            continue;
        }

        $violations[] = sprintf(
            'provider-size-gate: %s singleton=%d scoped=%d bind=%d bindIf=%d total=%d exceeds %s',
            $relative,
            $counts['singleton'],
            $counts['scoped'],
            $counts['bind'],
            $counts['bindIf'],
            $counts['total'],
            implode(' and ', $reasons),
        );
    }

    return $violations;
}

/**
 * @return array{ratchet: array<string, int>}
 */
function loadProviderSizeConfig(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "provider-size-gate: missing {$path}\n");
        exit(EXIT_CONFIG);
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        fwrite(STDERR, "provider-size-gate: invalid JSON in {$path}\n");
        exit(EXIT_CONFIG);
    }

    if (($decoded['max_bindings'] ?? null) !== MAX_BINDINGS) {
        fwrite(STDERR, 'provider-size-gate: max_bindings must be '.MAX_BINDINGS."\n");
        exit(EXIT_CONFIG);
    }

    if (($decoded['methods'] ?? null) !== BINDING_METHODS) {
        fwrite(STDERR, "provider-size-gate: methods must be singleton, scoped, bind, bindIf\n");
        exit(EXIT_CONFIG);
    }

    $ratchet = $decoded['ratchet'] ?? null;

    if (! is_array($ratchet)) {
        fwrite(STDERR, "provider-size-gate: invalid ratchet in {$path}\n");
        exit(EXIT_CONFIG);
    }

    $normalized = [];

    foreach ($ratchet as $file => $count) {
        if (! is_string($file) || $file === '' || ! is_int($count) || $count < 0) {
            fwrite(STDERR, "provider-size-gate: invalid ratchet entry in {$path}\n");
            exit(EXIT_CONFIG);
        }

        $file = str_replace('\\', '/', $file);

        if ($count > MAX_BINDINGS && $file !== CORE_PROVIDER) {
            fwrite(STDERR, 'provider-size-gate: ratchet above '.MAX_BINDINGS.' is only valid for '.CORE_PROVIDER."\n");
            exit(EXIT_CONFIG);
        }

        $normalized[$file] = $count;
    }

    return ['ratchet' => $normalized];
}

/**
 * @return array{singleton: int, scoped: int, bind: int, bindIf: int, total: int}|null
 */
function countBindingCalls(string $file): ?array
{
    $counts = array_fill_keys(BINDING_METHODS, 0);
    $code = file_get_contents($file);

    if ($code === false) {
        fwrite(STDERR, "provider-size-gate: unreadable {$file}\n");
        exit(EXIT_CONFIG);
    }

    try {
        $tokens = token_get_all($code, TOKEN_PARSE);
    } catch (ParseError $error) {
        fwrite(STDOUT, 'provider-size-gate: '.relativeFromRepo($file)." parse error\n");
        exit(EXIT_VIOLATION);
    }

    $uses = useImports($tokens);
    $count = count($tokens);
    $depth = 0;
    $providerDepths = [];
    $nextBraceIsProvider = false;
    $extendsProvider = false;

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if ($token === '{') {
            $depth++;

            if ($nextBraceIsProvider) {
                $providerDepths[] = $depth;
                $nextBraceIsProvider = false;
            }

            continue;
        }

        if ($token === '}') {
            if ($providerDepths !== [] && $providerDepths[array_key_last($providerDepths)] === $depth) {
                array_pop($providerDepths);
            }

            $depth--;

            continue;
        }

        if (! is_array($token) || $token[0] !== T_CLASS || isClassConstant($tokens, $i)) {
            if ($providerDepths !== [] && is_array($token) && $token[0] === T_OBJECT_OPERATOR) {
                $method = bindingMethodAt($tokens, $i);

                if ($method !== null) {
                    $counts[$method]++;
                }
            }

            continue;
        }

        $extends = extendedParent($tokens, $i, $uses) === PROVIDER_PARENT;
        $nextBraceIsProvider = $extends;
        $extendsProvider = $extendsProvider || $extends;
    }

    if (! $extendsProvider) {
        return null;
    }

    $counts['total'] = array_sum($counts);

    return $counts;
}

function relativeFromRepo(string $file): string
{
    $root = dirname(__DIR__);

    return str_replace('\\', '/', substr($file, strlen($root) + 1));
}

/**
 * @param  list<mixed>  $tokens
 */
function isClassConstant(array $tokens, int $classIndex): bool
{
    $previous = previousSignificant($tokens, $classIndex);

    return $previous !== null && is_array($tokens[$previous]) && $tokens[$previous][0] === T_DOUBLE_COLON;
}

/**
 * @param  list<mixed>  $tokens
 * @param  array<string, string>  $uses
 */
function extendedParent(array $tokens, int $classIndex, array $uses): ?string
{
    $count = count($tokens);
    $i = $classIndex + 1;

    while ($i < $count) {
        $token = $tokens[$i];

        if ($token === '{' || $token === ';') {
            return null;
        }

        if (is_array($token) && $token[0] === T_EXTENDS) {
            $name = readQualifiedName($tokens, $i + 1);

            return $name === null ? null : resolveType($name, $uses);
        }

        $i++;
    }

    return null;
}

/**
 * @param  list<mixed>  $tokens
 * @return array<string, string>
 */
function useImports(array $tokens): array
{
    $uses = [];
    $count = count($tokens);
    $depth = 0;

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if ($token === '{') {
            $depth++;

            continue;
        }

        if ($token === '}') {
            $depth--;

            continue;
        }

        if ($depth !== 0 || ! is_array($token) || $token[0] !== T_USE) {
            continue;
        }

        $next = nextSignificant($tokens, $i);

        if ($next === null) {
            continue;
        }

        $kind = $tokens[$next];

        if (is_array($kind) && in_array($kind[0], [T_FUNCTION, T_CONST], true)) {
            continue;
        }

        $prefix = readQualifiedName($tokens, $i + 1);

        if ($prefix === null) {
            continue;
        }

        $after = nextSignificant($tokens, indexAfterName($tokens, $i + 1));

        if ($after !== null && $tokens[$after] === '{') {
            $cursor = $after + 1;

            while ($cursor < $count && $tokens[$cursor] !== '}') {
                $short = readQualifiedName($tokens, $cursor);

                if ($short === null) {
                    $cursor++;

                    continue;
                }

                $alias = aliasAfter($tokens, indexAfterName($tokens, $cursor));
                $fqcn = ltrim($prefix.'\\'.$short, '\\');
                $uses[$alias ?? leafName($short)] = $fqcn;
                $cursor = indexAfterName($tokens, $cursor) + 1;
            }

            continue;
        }

        $alias = aliasAfter($tokens, indexAfterName($tokens, $i + 1));
        $uses[$alias ?? leafName($prefix)] = ltrim($prefix, '\\');
    }

    return $uses;
}

/**
 * @param  list<mixed>  $tokens
 */
function aliasAfter(array $tokens, int $afterName): ?string
{
    $as = nextSignificant($tokens, $afterName - 1);

    if ($as === null || ! is_array($tokens[$as]) || $tokens[$as][0] !== T_AS) {
        return null;
    }

    $alias = nextSignificant($tokens, $as);

    if ($alias === null || ! is_array($tokens[$alias]) || $tokens[$alias][0] !== T_STRING) {
        return null;
    }

    return $tokens[$alias][1];
}

function resolveType(string $name, array $uses): string
{
    $name = ltrim($name, '\\');

    if (str_contains($name, '\\')) {
        return $name;
    }

    return $uses[$name] ?? $name;
}

function leafName(string $fqcn): string
{
    $parts = explode('\\', ltrim($fqcn, '\\'));

    return (string) array_pop($parts);
}

/**
 * @param  list<mixed>  $tokens
 */
function readQualifiedName(array $tokens, int $start): ?string
{
    $i = nextSignificant($tokens, $start - 1);

    if ($i === null) {
        return null;
    }

    $token = $tokens[$i];

    if (! is_array($token)) {
        return null;
    }

    if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
        return $token[1];
    }

    if ($token[0] !== T_NS_SEPARATOR) {
        return null;
    }

    $parts = [];

    while ($i < count($tokens)) {
        $current = $tokens[$i];

        if ($current === '\\' || (is_array($current) && $current[0] === T_NS_SEPARATOR)) {
            $i++;

            continue;
        }

        if (! is_array($current) || $current[0] !== T_STRING) {
            break;
        }

        $parts[] = $current[1];
        $next = nextSignificant($tokens, $i);

        if ($next === null || $tokens[$next] !== '\\' && ! (is_array($tokens[$next]) && $tokens[$next][0] === T_NS_SEPARATOR)) {
            break;
        }

        $i = $next;
    }

    if ($parts === []) {
        return null;
    }

    return '\\'.implode('\\', $parts);
}

/**
 * @param  list<mixed>  $tokens
 */
function indexAfterName(array $tokens, int $start): int
{
    $i = nextSignificant($tokens, $start - 1);

    if ($i === null) {
        return $start;
    }

    $token = $tokens[$i];

    if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
        return $i;
    }

    if (! is_array($token) || $token[0] !== T_NS_SEPARATOR) {
        return $i;
    }

    while ($i < count($tokens)) {
        $next = nextSignificant($tokens, $i);

        if ($next === null || ! is_array($tokens[$next]) || $tokens[$next][0] !== T_STRING) {
            return $i;
        }

        $i = $next;
        $separator = nextSignificant($tokens, $i);

        if ($separator === null || $tokens[$separator] !== '\\' && ! (is_array($tokens[$separator]) && $tokens[$separator][0] === T_NS_SEPARATOR)) {
            return $i;
        }

        $i = $separator;
    }

    return $i;
}

/**
 * @param  list<mixed>  $tokens
 */
function bindingMethodAt(array $tokens, int $operatorIndex): ?string
{
    $methodIndex = nextSignificant($tokens, $operatorIndex);

    if ($methodIndex === null || ! is_array($tokens[$methodIndex]) || $tokens[$methodIndex][0] !== T_STRING) {
        return null;
    }

    $name = strtolower($tokens[$methodIndex][1]);

    if (! in_array($name, BINDING_METHODS, true)) {
        return null;
    }

    $paren = nextSignificant($tokens, $methodIndex);

    if ($paren === null || $tokens[$paren] !== '(') {
        return null;
    }

    return $name;
}

/**
 * @param  list<mixed>  $tokens
 */
function nextSignificant(array $tokens, int $index): ?int
{
    $count = count($tokens);

    for ($i = $index + 1; $i < $count; $i++) {
        $token = $tokens[$i];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $i;
    }

    return null;
}

/**
 * @param  list<mixed>  $tokens
 */
function previousSignificant(array $tokens, int $index): ?int
{
    for ($i = $index - 1; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $i;
    }

    return null;
}

/**
 * @return list<string>
 */
function walkPhpFiles(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || ! $file->isFile()) {
            continue;
        }

        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}
