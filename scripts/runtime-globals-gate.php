#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Scripts\RuntimeGlobalsGate;

use FilesystemIterator;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Blocking runtime-globals gate (P05.3, D70 п.5–9).
 *
 * G1: Illuminate\Support\Facades\* outside the bridge.
 * G2: global helper call (see "helpers" in the config) outside the bridge.
 * G3: static clock outside the bridge — Carbon[Immutable]::now/today/tomorrow/yesterday/parse,
 *     `new DateTime[Immutable]()` / `new Carbon[Immutable]()` without arguments or with 'now', `time()`.
 * G4: static property or `static $x` in a function body outside `Testing/`.
 * G5: `new class` anywhere in package src except `PHPStan/`.
 *
 * The bridge is `bridge_globs` (Laravel/**, *ServiceProvider.php). Tokens only: comments and
 * strings never count. The baseline is a ratchet limited to `baseline_prefixes` (P07 zeroes it).
 *
 *   --report                 print findings and the summary, exit 0
 *   --write-baseline         write findings under baseline_prefixes; refuse anything else
 *   --config=<path>          alternative config (negative controls)
 *   --baseline=<path>        alternative baseline (negative controls)
 *
 * Exit: 0 OK, 1 violation, 2 configuration error.
 */
const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

const RULES = ['G1', 'G2', 'G3', 'G4', 'G5'];
const FACADES_PREFIX = 'Illuminate\\Support\\Facades';
const CARBON_CLASSES = ['Carbon', 'CarbonImmutable'];
const CARBON_CLOCK_METHODS = ['now', 'today', 'tomorrow', 'yesterday', 'parse'];
const CLOCK_CLASSES = ['DateTime', 'DateTimeImmutable', 'Carbon', 'CarbonImmutable'];

require_once __DIR__.'/support/cli-arguments.php';

$repoRoot = dirname(__DIR__);
$options = parseOptions(\CoreX\Scripts\cliArguments());
$config = loadConfig(optionPath($options, 'config', $repoRoot.'/scripts/runtime-globals.json'));
$baselinePath = optionPath($options, 'baseline', $repoRoot.'/scripts/runtime-globals-baseline.json');
$findings = collectFindings($repoRoot, $config);

if (isset($options['report'])) {
    foreach ($findings as $finding) {
        fwrite(STDOUT, formatFinding($finding)."\n");
    }

    fwrite(STDOUT, 'runtime-globals-gate: report '.summarise($findings)."\n");
    exit(EXIT_OK);
}

if (isset($options['write-baseline'])) {
    exit(writeBaseline($baselinePath, $findings, $config['baseline_prefixes']));
}

$baseline = loadBaseline($baselinePath, $config['baseline_prefixes']);
[$outside, $problems] = judge($findings, $baseline);

foreach ($outside as $finding) {
    fwrite(STDOUT, formatFinding($finding)."\n");
}

foreach ($problems as $problem) {
    fwrite(STDOUT, $problem."\n");
}

$counts = countByRule($outside);
fwrite(STDOUT, 'runtime-globals-gate: '.implode(', ', array_map(
    static fn (string $rule): string => $rule.' '.$counts[$rule],
    RULES,
))." outside baseline\n");

$baselineFindings = array_sum(array_map(array_sum(...), $baseline));
fwrite(STDOUT, sprintf(
    "runtime-globals-gate: baseline files=%d findings=%d (prefix %s)\n",
    count($baseline),
    $baselineFindings,
    implode(', ', $config['baseline_prefixes']),
));

if ($outside !== [] || $problems !== []) {
    exit(EXIT_VIOLATION);
}

fwrite(STDOUT, "runtime-globals-gate: OK\n");
exit(EXIT_OK);

/**
 * @param  list<string>  $argv
 * @return array<string, string|true>
 */
function parseOptions(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $argument) {
        if (! str_starts_with($argument, '--')) {
            fail("unknown argument {$argument}");
        }

        $parts = explode('=', substr($argument, 2), 2);

        if (! in_array($parts[0], ['report', 'write-baseline', 'config', 'baseline'], true)) {
            fail("unknown option --{$parts[0]}");
        }

        $options[$parts[0]] = $parts[1] ?? true;
    }

    return $options;
}

/**
 * @param  array<string, string|true>  $options
 */
function optionPath(array $options, string $name, string $default): string
{
    $value = $options[$name] ?? $default;

    if ($value === true || $value === '') {
        fail("option --{$name} needs a path");
    }

    return $value;
}

function fail(string $message): never
{
    fwrite(STDERR, "runtime-globals-gate: {$message}\n");
    exit(EXIT_CONFIG);
}

/**
 * @return array{bridge_globs: list<string>, testing_globs: list<string>, phpstan_globs: list<string>, helpers: list<string>, baseline_prefixes: list<string>}
 */
function loadConfig(string $path): array
{
    if (! is_file($path)) {
        fail("missing config {$path}");
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        fail("config is not valid JSON: {$path}");
    }

    $lists = [];

    foreach (['bridge_globs', 'testing_globs', 'phpstan_globs', 'helpers', 'baseline_prefixes'] as $key) {
        $lists[$key] = [];
        $value = $decoded[$key] ?? null;

        if (! is_array($value)) {
            fail("config key {$key} must be a list of non-empty strings");
        }

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                fail("config key {$key} must be a list of non-empty strings");
            }

            $lists[$key][] = $key === 'helpers' ? strtolower($item) : $item;
        }
    }

    return [
        'bridge_globs' => $lists['bridge_globs'],
        'testing_globs' => $lists['testing_globs'],
        'phpstan_globs' => $lists['phpstan_globs'],
        'helpers' => $lists['helpers'],
        'baseline_prefixes' => $lists['baseline_prefixes'],
    ];
}

/**
 * @param  list<string>  $prefixes
 * @return array<string, array<string, int>>
 */
function loadBaseline(string $path, array $prefixes): array
{
    if (! is_file($path)) {
        fail("missing baseline {$path}");
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (! is_array($decoded)) {
        fail("baseline is not valid JSON: {$path}");
    }

    $baseline = [];

    foreach ($decoded as $file => $rules) {
        if (! is_string($file) || ! is_array($rules)) {
            fail('baseline entries must map a file to a rule-count map');
        }

        if (! startsWithAny($file, $prefixes)) {
            fail("baseline path outside baseline_prefixes: {$file}");
        }

        foreach ($rules as $rule => $count) {
            if (! in_array($rule, RULES, true) || ! is_int($count) || $count < 1) {
                fail("baseline {$file} has an invalid entry for {$rule}");
            }

            $baseline[$file][$rule] = $count;
        }
    }

    ksort($baseline);

    return $baseline;
}

/**
 * @param  list<string>  $prefixes
 */
function startsWithAny(string $path, array $prefixes): bool
{
    return array_any($prefixes, fn ($prefix) => str_starts_with($path, $prefix));
}

/**
 * @param  list<array{rule: string, file: string, line: int, token: string}>  $findings
 * @param  list<string>  $prefixes
 */
function writeBaseline(string $path, array $findings, array $prefixes): int
{
    $foreign = array_values(array_filter($findings, static fn (array $f): bool => ! startsWithAny($f['file'], $prefixes)));

    if ($foreign !== []) {
        foreach ($foreign as $finding) {
            fwrite(STDOUT, formatFinding($finding)."\n");
        }

        fwrite(STDOUT, 'runtime-globals-gate: refusing to write baseline, findings outside '.implode(', ', $prefixes)."\n");

        return EXIT_VIOLATION;
    }

    $baseline = [];

    foreach ($findings as $finding) {
        $baseline[$finding['file']][$finding['rule']] = ($baseline[$finding['file']][$finding['rule']] ?? 0) + 1;
    }

    ksort($baseline);

    foreach ($baseline as &$rules) {
        ksort($rules);
    }

    unset($rules);

    file_put_contents($path, json_encode((object) $baseline, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    fwrite(STDOUT, sprintf("runtime-globals-gate: baseline written files=%d findings=%d\n", count($baseline), count($findings)));

    return EXIT_OK;
}

/**
 * @param  list<array{rule: string, file: string, line: int, token: string}>  $findings
 * @param  array<string, array<string, int>>  $baseline
 * @return array{0: list<array{rule: string, file: string, line: int, token: string}>, 1: list<string>}
 */
function judge(array $findings, array $baseline): array
{
    $outside = [];
    $problems = [];
    $seen = [];

    foreach ($findings as $finding) {
        $allowed = $baseline[$finding['file']][$finding['rule']] ?? 0;

        if ($allowed === 0) {
            $outside[] = $finding;

            continue;
        }

        $seen[$finding['file']][$finding['rule']] = ($seen[$finding['file']][$finding['rule']] ?? 0) + 1;
    }

    foreach ($baseline as $file => $rules) {
        foreach ($rules as $rule => $allowed) {
            $found = $seen[$file][$rule] ?? 0;

            if ($found > $allowed) {
                $problems[] = "{$rule} {$file} baseline exceeded: {$found} > {$allowed}";
            } elseif ($found < $allowed) {
                $problems[] = "{$rule} {$file} baseline stale: lower it from {$allowed} to {$found}";
            }
        }
    }

    return [$outside, $problems];
}

/**
 * @param  list<array{rule: string, file: string, line: int, token: string}>  $findings
 * @return array<string, int>
 */
function countByRule(array $findings): array
{
    $counts = array_fill_keys(RULES, 0);

    foreach ($findings as $finding) {
        $counts[$finding['rule']]++;
    }

    return $counts;
}

/**
 * @param  list<array{rule: string, file: string, line: int, token: string}>  $findings
 */
function summarise(array $findings): string
{
    $counts = countByRule($findings);

    return implode(', ', array_map(static fn (string $rule): string => $rule.' '.$counts[$rule], RULES))
        .' files='.count(array_unique(array_column($findings, 'file')));
}

/**
 * @param  array{rule: string, file: string, line: int, token: string}  $finding
 */
function formatFinding(array $finding): string
{
    return sprintf('%s %s:%d %s', $finding['rule'], $finding['file'], $finding['line'], $finding['token']);
}

/**
 * @param  array{bridge_globs: list<string>, testing_globs: list<string>, phpstan_globs: list<string>, helpers: list<string>, baseline_prefixes: list<string>}  $config
 * @return list<array{rule: string, file: string, line: int, token: string}>
 */
function collectFindings(string $repoRoot, array $config): array
{
    $findings = [];

    $sourceRoots = glob($repoRoot.'/packages/*/src', GLOB_ONLYDIR);

    foreach ($sourceRoots === false ? [] : $sourceRoots as $srcRoot) {
        foreach (walkPhpFiles($srcRoot) as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($repoRoot) + 1));
            $packageRelative = substr($relative, strlen('packages/'));
            $inBridge = matchesAnyGlob($packageRelative, $config['bridge_globs']);
            $inTesting = matchesAnyGlob($packageRelative, $config['testing_globs']);
            $inPhpstan = matchesAnyGlob($packageRelative, $config['phpstan_globs']);

            foreach (scanFile($file, $config['helpers']) as $finding) {
                $exempt = match ($finding['rule']) {
                    'G1', 'G2', 'G3' => $inBridge,
                    'G4' => $inTesting,
                    'G5' => $inPhpstan,
                    default => false,
                };

                if (! $exempt) {
                    $findings[] = ['rule' => $finding['rule'], 'file' => $relative, 'line' => $finding['line'], 'token' => $finding['token']];
                }
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
        $rendered = '';
        $length = strlen($glob);

        for ($i = 0; $i < $length; $i++) {
            if (substr($glob, $i, 3) === '**/') {
                $rendered .= '(?:.*/)?';
                $i += 2;
            } elseif (substr($glob, $i, 2) === '**') {
                $rendered .= '.*';
                $i += 1;
            } elseif ($glob[$i] === '*') {
                $rendered .= '[^/]*';
            } elseif ($glob[$i] === '?') {
                $rendered .= '[^/]';
            } else {
                $rendered .= preg_quote($glob[$i], '#');
            }
        }

        if (preg_match('#^'.$rendered.'$#', $path) === 1) {
            return true;
        }
    }

    return false;
}

/**
 * @param  list<string>  $helpers
 * @return list<array{rule: string, line: int, token: string}>
 */
function scanFile(string $file, array $helpers): array
{
    $code = file_get_contents($file);

    if ($code === false) {
        fail("unreadable {$file}");
    }

    try {
        $tokens = token_get_all($code, TOKEN_PARSE);
    } catch (ParseError $error) {
        fail("parse error in {$file}: {$error->getMessage()}");
    }

    $findings = [];
    $count = count($tokens);
    $imports = collectImports($tokens);

    foreach ($imports['facades'] as $facadeLine => $facade) {
        $findings[] = ['rule' => 'G1', 'line' => $facadeLine, 'token' => $facade];
    }

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (! is_array($token)) {
            continue;
        }

        $line = $token[2];

        if (isNameToken($token)) {
            $name = ltrim($token[1], '\\');

            $resolved = resolveClassName($token[1], $imports['class']);

            // The import line of a facade is the finding; a short imported name later in the file is not counted again.
            // A qualified name that only becomes a facade through a namespace alias has no import line of its own.
            $isFacade = strcasecmp($resolved, FACADES_PREFIX) === 0 || stripos($resolved, FACADES_PREFIX.'\\') === 0;
            $viaNamespaceAlias = str_contains($name, '\\') && $resolved !== $name;

            if ($isFacade && (stripos($name, FACADES_PREFIX) === 0 || $viaNamespaceAlias)) {
                $findings[] = ['rule' => 'G1', 'line' => $line, 'token' => $resolved];

                continue;
            }

            $function = str_contains($name, '\\') ? null : ($imports['function'][strtolower($name)] ?? null);
            $helper = helperCall($tokens, $i, $function !== null ? leafName($function) : $name, $helpers);

            if ($helper === 'time') {
                $findings[] = ['rule' => 'G3', 'line' => $line, 'token' => 'time()'];
            } elseif ($helper !== null) {
                $findings[] = ['rule' => 'G2', 'line' => $line, 'token' => $helper.'()'];
            }

            $clock = staticClockCall($tokens, $i, $resolved);

            if ($clock !== null) {
                $findings[] = ['rule' => 'G3', 'line' => $line, 'token' => $clock];
            }

            continue;
        }

        if ($token[0] === T_NEW) {
            $findings = [...$findings, ...newExpressionFindings($tokens, $i, $line, $imports['class'])];

            continue;
        }

        if ($token[0] === T_STATIC && staticDeclaresVariable($tokens, $i)) {
            $findings[] = ['rule' => 'G4', 'line' => $line, 'token' => 'static '.staticVariableName($tokens, $i)];
        }
    }

    return $findings;
}

/**
 * Fully qualified name of a class reference: applies `use` aliases to the first segment, case-insensitively
 * (PHP class and namespace names are), so `use A\B as C; C\D` and `MOMENT::now()` resolve like the plain form.
 *
 * @param  array<string, string>  $classImports  lower-cased alias => fully qualified name
 */
function resolveClassName(string $name, array $classImports): string
{
    if (str_starts_with($name, '\\')) {
        return ltrim($name, '\\');
    }

    $separator = strpos($name, '\\');
    $head = $separator === false ? $name : substr($name, 0, $separator);
    $imported = $classImports[strtolower($head)] ?? null;

    if ($imported === null) {
        return $name;
    }

    return $separator === false ? $imported : $imported.substr($name, $separator);
}

function leafName(string $name): string
{
    return substr($name, (int) strrpos('\\'.$name, '\\'));
}

/**
 * Resolves `use` statements: class aliases, function aliases (`use function now as clockNow`) and
 * facade imports hidden in group syntax (`use Illuminate\Support\{Facades\DB}`).
 *
 * @param  list<string|array{int, string, int}>  $tokens
 * @return array{class: array<string, string>, function: array<string, string>, facades: array<int, string>}
 */
function collectImports(array $tokens): array
{
    $imports = ['class' => [], 'function' => [], 'facades' => []];
    $count = count($tokens);
    $depth = 0;

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if ($token === '{') {
            $depth++;
        } elseif ($token === '}') {
            $depth--;
        }

        if (! is_array($token) || $token[0] !== T_USE) {
            continue;
        }

        $previous = previousSignificant($tokens, $i);
        $before = $previous === null ? null : $tokens[$previous];

        // Closure `use (...)` and trait `use` inside a class body are not imports.
        if ($before === ')' || ($depth > 0 && ! isNamespaceBlock($tokens, $i))) {
            continue;
        }

        $kind = 'class';
        $cursor = nextSignificant($tokens, $i);

        if ($cursor !== null && is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], [T_FUNCTION, T_CONST], true)) {
            $kind = $tokens[$cursor][0] === T_FUNCTION ? 'function' : 'const';
            $cursor = nextSignificant($tokens, $cursor);
        }

        $prefix = '';
        $itemKind = $kind;
        $name = null;
        $alias = null;
        $nameLine = $token[2];
        $inGroup = false;
        $expectAlias = false;

        for (; $cursor !== null && $tokens[$cursor] !== ';'; $cursor = nextSignificant($tokens, $cursor)) {
            $current = $tokens[$cursor];

            if ($current === '{') {
                $prefix = $name ?? '';
                $name = null;
                $inGroup = true;

                continue;
            }

            if ($current === ',' || $current === '}') {
                recordImport($imports, $itemKind, $prefix, $name, $alias, $nameLine);
                $name = null;
                $alias = null;
                $expectAlias = false;
                $itemKind = $kind;

                continue;
            }

            if (! is_array($current)) {
                continue;
            }

            if ($current[0] === T_AS) {
                $expectAlias = true;

                continue;
            }

            if ($inGroup && in_array($current[0], [T_FUNCTION, T_CONST], true)) {
                $itemKind = $current[0] === T_FUNCTION ? 'function' : 'const';

                continue;
            }

            if ($expectAlias && $current[0] === T_STRING) {
                $alias = $current[1];
                $expectAlias = false;

                continue;
            }

            if (isNameToken($current)) {
                $name = $current[1];
                $nameLine = $current[2];
            }
        }

        recordImport($imports, $itemKind, $prefix, $name, $alias, $nameLine);
    }

    return $imports;
}

/**
 * @param  array{class: array<string, string>, function: array<string, string>, facades: array<int, string>}  $imports
 */
function recordImport(array &$imports, string $kind, string $prefix, ?string $name, ?string $alias, int $line): void
{
    if ($name === null || $name === '') {
        return;
    }

    $full = ltrim($prefix === '' ? $name : rtrim($prefix, '\\').'\\'.ltrim($name, '\\'), '\\');

    if ($prefix !== '' && ($full === FACADES_PREFIX || str_starts_with($full, FACADES_PREFIX.'\\'))) {
        $imports['facades'][$line] = $full;
    }

    if ($kind === 'const') {
        return;
    }

    $key = $alias ?? leafName($full);

    if ($kind === 'function') {
        $imports['function'][strtolower($key)] = $full;

        return;
    }

    $imports['class'][strtolower($key)] = $full;
}

/**
 * @param  list<string|array{int, string, int}>  $tokens
 */
function isNamespaceBlock(array $tokens, int $index): bool
{
    for ($i = $index - 1; $i >= 0; $i--) {
        if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
            return false;
        }

        if (is_array($tokens[$i]) && $tokens[$i][0] === T_NAMESPACE) {
            return true;
        }
    }

    return true;
}

/**
 * @param  array{0: int, 1: string, 2: int}  $token
 */
function isNameToken(array $token): bool
{
    return in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true);
}

/**
 * Global helper (or `time`) called as a plain function: not a method, static member,
 * declaration, constant or `new` target.
 *
 * @param  list<string|array{int, string, int}>  $tokens
 * @param  list<string>  $helpers
 */
function helperCall(array $tokens, int $index, string $name, array $helpers): ?string
{
    $lower = strtolower($name);

    if ($lower !== 'time' && ! in_array($lower, $helpers, true)) {
        return null;
    }

    if (str_contains($name, '\\')) {
        return null;
    }

    $next = nextSignificant($tokens, $index);

    if ($next === null || $tokens[$next] !== '(') {
        return null;
    }

    $previous = previousSignificant($tokens, $index);

    if ($previous !== null) {
        $before = $tokens[$previous];

        if (is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST, T_USE], true)) {
            return null;
        }

        if ($before === '&') {
            $beforeAmpersand = previousSignificant($tokens, $previous);

            if ($beforeAmpersand !== null && is_array($tokens[$beforeAmpersand]) && $tokens[$beforeAmpersand][0] === T_FUNCTION) {
                return null;
            }
        }
    }

    return $lower;
}

/**
 * `Carbon::now()` and friends, by short or qualified class name.
 *
 * @param  list<string|array{int, string, int}>  $tokens
 */
function staticClockCall(array $tokens, int $index, string $name): ?string
{
    $leaf = substr($name, (int) strrpos('\\'.$name, '\\'));

    if (! in_array(strtolower($leaf), array_map(strtolower(...), CARBON_CLASSES), true)) {
        return null;
    }

    $colon = nextSignificant($tokens, $index);

    if ($colon === null || ! is_array($tokens[$colon]) || $tokens[$colon][0] !== T_DOUBLE_COLON) {
        return null;
    }

    $method = nextSignificant($tokens, $colon);

    if ($method === null || ! is_array($tokens[$method]) || $tokens[$method][0] !== T_STRING) {
        return null;
    }

    if (! in_array(strtolower($tokens[$method][1]), CARBON_CLOCK_METHODS, true)) {
        return null;
    }

    $paren = nextSignificant($tokens, $method);

    return $paren !== null && $tokens[$paren] === '(' ? $leaf.'::'.$tokens[$method][1] : null;
}

/**
 * @param  list<string|array{int, string, int}>  $tokens
 * @param  array<string, string>  $classImports  alias => fully qualified class name
 * @return list<array{rule: string, line: int, token: string}>
 */
function newExpressionFindings(array $tokens, int $newIndex, int $line, array $classImports): array
{
    $next = nextSignificant($tokens, $newIndex);

    while ($next !== null && is_array($tokens[$next]) && in_array($tokens[$next][0], [T_READONLY, T_ATTRIBUTE], true)) {
        if ($tokens[$next][0] === T_ATTRIBUTE) {
            $next = skipAttribute($tokens, $next);

            if ($next === null) {
                return [];
            }
        }

        $next = nextSignificant($tokens, $next);
    }

    if ($next === null || ! is_array($tokens[$next])) {
        return [];
    }

    if ($tokens[$next][0] === T_CLASS) {
        return [['rule' => 'G5', 'line' => $line, 'token' => 'new class']];
    }

    if (! isNameToken($tokens[$next])) {
        return [];
    }

    $name = resolveClassName($tokens[$next][1], $classImports);
    $leaf = leafName($name);

    if (! in_array(strtolower($leaf), array_map(strtolower(...), CLOCK_CLASSES), true)) {
        return [];
    }

    $paren = nextSignificant($tokens, $next);

    if ($paren === null) {
        return [];
    }

    // `new DateTimeImmutable;` — the style Pint enforces for a constructor without arguments.
    if (in_array($tokens[$paren], [';', ')', ',', ']'], true)) {
        return [['rule' => 'G3', 'line' => $line, 'token' => 'new '.$leaf.'()']];
    }

    if ($tokens[$paren] !== '(') {
        return [];
    }

    $first = nextSignificant($tokens, $paren);

    if ($first === null) {
        return [];
    }

    $readsNow = $tokens[$first] === ')';

    if (! $readsNow && is_array($tokens[$first]) && $tokens[$first][0] === T_CONSTANT_ENCAPSED_STRING && strtolower(trim($tokens[$first][1], '\'"')) === 'now') {
        $close = nextSignificant($tokens, $first);
        $readsNow = $close !== null && ($tokens[$close] === ')' || $tokens[$close] === ',');
    }

    return $readsNow ? [['rule' => 'G3', 'line' => $line, 'token' => 'new '.$leaf.'()']] : [];
}

/**
 * @param  list<string|array{int, string, int}>  $tokens
 */
function skipAttribute(array $tokens, int $attributeIndex): ?int
{
    $depth = 0;
    $count = count($tokens);

    for ($i = $attributeIndex; $i < $count; $i++) {
        $token = $tokens[$i];

        if (is_array($token) && $token[0] === T_ATTRIBUTE) {
            $depth++;
        } elseif ($token === '[') {
            $depth++;
        } elseif ($token === ']') {
            $depth--;

            if ($depth === 0) {
                return $i;
            }
        }
    }

    return null;
}

/**
 * `static` followed by (modifiers and) a type and a variable before any call, closure or scope operator.
 *
 * @param  list<string|array{int, string, int}>  $tokens
 */
function staticDeclaresVariable(array $tokens, int $staticIndex): bool
{
    return staticVariableName($tokens, $staticIndex) !== null;
}

/**
 * @param  list<string|array{int, string, int}>  $tokens
 */
function staticVariableName(array $tokens, int $staticIndex): ?string
{
    $previous = previousSignificant($tokens, $staticIndex);

    if ($previous !== null && is_array($tokens[$previous]) && in_array($tokens[$previous][0], [T_NEW, T_INSTANCEOF], true)) {
        return null;
    }

    $cursor = nextSignificant($tokens, $staticIndex);

    while ($cursor !== null) {
        $token = $tokens[$cursor];

        if (is_array($token) && $token[0] === T_VARIABLE) {
            return $token[1];
        }

        $isTypePart = is_string($token)
            ? in_array($token, ['?', '|', '&'], true)
            : isNameToken($token) || in_array($token[0], [T_ARRAY, T_CALLABLE, T_STATIC, T_READONLY, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR, T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG, T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG], true);

        if (! $isTypePart) {
            return null;
        }

        $cursor = nextSignificant($tokens, $cursor);
    }

    return null;
}

/**
 * @param  list<string|array{int, string, int}>  $tokens
 */
function nextSignificant(array $tokens, int $index): ?int
{
    $count = count($tokens);

    for ($i = $index + 1; $i < $count; $i++) {
        if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        return $i;
    }

    return null;
}

/**
 * @param  list<string|array{int, string, int}>  $tokens
 */
function previousSignificant(array $tokens, int $index): ?int
{
    for ($i = $index - 1; $i >= 0; $i--) {
        if (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
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
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}
