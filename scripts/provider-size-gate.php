#!/usr/bin/env php
<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;

/**
 * Blocking provider-size gate (P8.6, D45; thin providers P05.2, D70).
 *
 * S1: singleton|scoped|bind|bindIf call sites only inside classes that extend
 * Illuminate\Support\ServiceProvider; a foreach body is one call site; at most
 * max_bindings per provider and never above the ratchet.
 * S2: no `new class` in a provider or registrar.
 * S3: every `function` closure in a provider or registrar is a single
 * `return ...;` statement (`fn` is always allowed).
 * `--report` prints the measured snapshot and does not judge it.
 */
const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

const PROVIDER_PARENT = ServiceProvider::class;
const BINDING_METHODS = ['singleton', 'scoped', 'bind', 'bindIf'];
const MAX_BINDINGS = 8;

$repoRoot = dirname(__DIR__);
$configPath = $repoRoot.'/scripts/provider-size.json';
$reportOnly = in_array('--report', $argv, true);

$config = loadProviderSizeConfig($configPath);
$measured = measureProviders($repoRoot);
$findings = styleFindings($repoRoot, $config['registrar_globs']);

if ($reportOnly) {
    fwrite(STDOUT, json_encode(['providers' => $measured, 'style' => $findings], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    exit(EXIT_OK);
}

$violations = [...judgeProviders($measured, $config['ratchet']), ...array_map(
    static fn (array $finding): string => sprintf('%s %s:%d %s', $finding['rule'], $finding['file'], $finding['line'], $finding['message']),
    $findings,
)];

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

    foreach ((($entries = glob($repoRoot.'/packages/*/src', GLOB_ONLYDIR)) === false ? [] : $entries) as $srcRoot) {
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

        if ($counts['total'] > MAX_BINDINGS) {
            $reasons[] = 'max_bindings='.MAX_BINDINGS;
        }

        $frozen = $ratchet[$relative] ?? null;

        if ($frozen !== null && $counts['total'] > $frozen) {
            $reasons[] = 'ratchet='.$frozen;
        }

        if ($reasons === []) {
            continue;
        }

        $violations[] = sprintf(
            'S1 %s singleton=%d scoped=%d bind=%d bindIf=%d total=%d %s',
            $relative,
            $counts['singleton'],
            $counts['scoped'],
            $counts['bind'],
            $counts['bindIf'],
            $counts['total'],
            implode(' ', $reasons),
        );
    }

    return $violations;
}

/**
 * @return array{ratchet: array<string, int>, registrar_globs: list<string>}
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

    if (($decoded['forbid_anonymous_classes'] ?? null) !== true || ($decoded['single_return_factories'] ?? null) !== true) {
        fwrite(STDERR, "provider-size-gate: forbid_anonymous_classes and single_return_factories must be true\n");
        exit(EXIT_CONFIG);
    }

    $globs = $decoded['registrar_globs'] ?? null;

    if (! is_array($globs) || $globs === [] || array_filter($globs, static fn (mixed $glob): bool => ! is_string($glob) || $glob === '') !== []) {
        fwrite(STDERR, "provider-size-gate: invalid registrar_globs in {$path}\n");
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

        if ($count > MAX_BINDINGS) {
            fwrite(STDERR, 'provider-size-gate: ratchet above '.MAX_BINDINGS." is not allowed\n");
            exit(EXIT_CONFIG);
        }

        $normalized[$file] = $count;
    }

    /** @var list<string> $registrarGlobs */
    $registrarGlobs = array_values($globs);

    return ['ratchet' => $normalized, 'registrar_globs' => $registrarGlobs];
}

/**
 * S2 and S3 over every provider and registrar file.
 *
 * @param  list<string>  $registrarGlobs
 * @return list<array{rule: string, file: string, line: int, message: string}>
 */
function styleFindings(string $repoRoot, array $registrarGlobs): array
{
    $findings = [];

    $roots = glob($repoRoot.'/packages/*/src', GLOB_ONLYDIR);

    foreach ($roots === false ? [] : $roots as $srcRoot) {
        foreach (walkPhpFiles($srcRoot) as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($repoRoot) + 1));
            $isRegistrar = matchesAnyGlob(substr($relative, strlen('packages/')), $registrarGlobs);

            if (! $isRegistrar && countBindingCalls($file) === null) {
                continue;
            }

            foreach (scanStyle($file) as $finding) {
                $findings[] = ['rule' => $finding['rule'], 'file' => $relative, 'line' => $finding['line'], 'message' => $finding['message']];
            }
        }
    }

    usort($findings, static fn (array $a, array $b): int => [$a['file'], $a['line'], $a['rule']] <=> [$b['file'], $b['line'], $b['rule']]);

    return $findings;
}

/**
 * @param  list<string>  $globs
 */
function matchesAnyGlob(string $path, array $globs): bool
{
    foreach ($globs as $glob) {
        $segments = explode('/', str_replace('\\', '/', $glob));
        $rendered = '';
        $last = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if ($segment === '**') {
                $rendered .= '(?:[^/]+/)*';

                continue;
            }

            $rendered .= str_replace(['\*', '\?'], ['[^/]*', '[^/]'], preg_quote($segment, '#')).($index !== $last ? '/' : '');
        }

        if (preg_match('#^'.$rendered.'$#', $path) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * @return list<array{rule: string, line: int, message: string}>
 */
function scanStyle(string $file): array
{
    $code = file_get_contents($file);

    if ($code === false) {
        fwrite(STDERR, "provider-size-gate: unreadable {$file}\n");
        exit(EXIT_CONFIG);
    }

    try {
        $tokens = token_get_all($code, TOKEN_PARSE);
    } catch (ParseError) {
        fwrite(STDOUT, 'provider-size-gate: '.relativeFromRepo($file)." parse error\n");
        exit(EXIT_VIOLATION);
    }

    $findings = [];
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token)) {
            continue;
        }

        if ($token[0] === T_NEW && anonymousClassFollows($tokens, $i)) {
            $findings[] = ['rule' => 'S2', 'line' => $token[2], 'message' => 'anonymous class'];

            continue;
        }

        if ($token[0] === T_FUNCTION && ! isNamedFunction($tokens, $i) && ! closureBodyIsSingleReturn($tokens, $i)) {
            $findings[] = ['rule' => 'S3', 'line' => $token[2], 'message' => 'factory closure is not a single return'];
        }
    }

    return $findings;
}

/**
 * @param  list<array{int, string, int}|string>  $tokens
 */
function anonymousClassFollows(array $tokens, int $newIndex): bool
{
    $next = nextSignificant($tokens, $newIndex);

    while ($next !== null && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_READONLY, T_ATTRIBUTE], true)) {
        if ($tokens[$next][0] === T_ATTRIBUTE) {
            return true;
        }

        $next = nextSignificant($tokens, $next);
    }

    return $next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_CLASS;
}

/**
 * A method or function declaration has a name after `function`; a closure goes straight to `(`.
 *
 * @param  list<array{int, string, int}|string>  $tokens
 */
function isNamedFunction(array $tokens, int $functionIndex): bool
{
    $next = nextSignificant($tokens, $functionIndex);

    if ($next !== null && $tokens[$next] === '&') {
        $next = nextSignificant($tokens, $next);
    }

    return $next !== null && $tokens[$next] !== '(';
}

/**
 * @param  list<array{int, string, int}|string>  $tokens
 */
function closureBodyIsSingleReturn(array $tokens, int $functionIndex): bool
{
    $count = count($tokens);
    $i = nextSignificant($tokens, $functionIndex);

    if ($i !== null && $tokens[$i] === '&') {
        $i = nextSignificant($tokens, $i);
    }

    // Parameter list, then an optional `use (...)` list.
    $i = skipParenthesised($tokens, (int) $i);
    $next = $i === null ? null : nextSignificant($tokens, $i);

    if ($next !== null && is_array($tokens[$next]) && $tokens[$next][0] === T_USE) {
        $open = nextSignificant($tokens, $next);
        $i = $open === null ? null : skipParenthesised($tokens, $open);
    }

    // Optional return type up to the body brace.
    $cursor = $i === null ? null : nextSignificant($tokens, $i);

    while ($cursor !== null && $tokens[$cursor] !== '{') {
        if ($tokens[$cursor] === ';') {
            return true;
        }

        $cursor = nextSignificant($tokens, $cursor);
    }

    if ($cursor === null) {
        return true;
    }

    $depth = 0;
    $statements = 0;
    $first = null;
    $lastSignificant = null;

    for ($j = $cursor; $j < $count; $j++) {
        $token = $tokens[$j];

        if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;

            continue;
        }

        if ($token === '}') {
            $depth--;

            if ($depth === 0) {
                break;
            }

            continue;
        }

        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        if ($depth === 1) {
            $first ??= $token;
            $lastSignificant = $token;

            if ($token === ';') {
                $statements++;
            }
        }
    }

    return is_array($first) && $first[0] === T_RETURN && $statements === 1 && $lastSignificant === ';';
}

/**
 * @param  list<array{int, string, int}|string>  $tokens
 */
function skipParenthesised(array $tokens, int $openIndex): ?int
{
    $count = count($tokens);
    $depth = 0;

    for ($i = $openIndex; $i < $count; $i++) {
        if ($tokens[$i] === '(') {
            $depth++;
        } elseif ($tokens[$i] === ')') {
            $depth--;

            if ($depth === 0) {
                return $i;
            }
        }
    }

    return null;
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
    } catch (ParseError) {
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

    return ['singleton' => $counts['singleton'], 'scoped' => $counts['scoped'], 'bind' => $counts['bind'], 'bindIf' => $counts['bindIf'], 'total' => array_sum($counts)];
}

function relativeFromRepo(string $file): string
{
    $root = dirname(__DIR__);

    return str_replace('\\', '/', substr($file, strlen($root) + 1));
}

/**
 * @param  list<array{int, string, int}|string>  $tokens
 */
function isClassConstant(array $tokens, int $classIndex): bool
{
    $previous = previousSignificant($tokens, $classIndex);

    return $previous !== null && is_array($tokens[$previous]) && $tokens[$previous][0] === T_DOUBLE_COLON;
}

/**
 * @param  list<array{int, string, int}|string>  $tokens
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
 * @param  list<array{int, string, int}|string>  $tokens
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
 * @param  list<array{int, string, int}|string>  $tokens
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

/** @param array<string, string> $uses */
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

    return array_pop($parts);
}

/**
 * @param  list<array{int, string, int}|string>  $tokens
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
 * @param  list<array{int, string, int}|string>  $tokens
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
 * @param  list<array{int, string, int}|string>  $tokens
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
 * @param  list<array{int, string, int}|string>  $tokens
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
 * @param  list<array{int, string, int}|string>  $tokens
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
