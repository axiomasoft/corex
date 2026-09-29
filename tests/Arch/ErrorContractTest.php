<?php

declare(strict_types=1);

use CoreX\Tests\Support\SourceScanner;

test('P12 public error declarations match the stable contract fixture', function (): void {
    expect(p12ErrorContractViolations())->toBe([]);
});

test('P12 error gate rejects an unregistered public code', function (): void {
    $path = SourceScanner::packagePath('operations', 'src/Definition/P12UnregisteredErrorProbe.php');

    file_put_contents($path, <<<'PHP'
<?php
new ApiError('p12_unregistered_probe', 409, 'probe');
PHP);

    try {
        expect(p12ErrorContractViolations())->toContain('E-UNREGISTERED-CODE axiomasoft/corex-operations p12_unregistered_probe');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('P12 error gate rejects category drift for a registered code', function (): void {
    $path = SourceScanner::packagePath('operations', 'src/Definition/P12CategoryDriftProbe.php');

    file_put_contents($path, <<<'PHP'
<?php
new ApiError('validation_failed', 409, 'probe');
PHP);

    try {
        expect(p12ErrorContractViolations())->toContain('E-CONTRACT-DRIFT axiomasoft/corex-operations validation_failed');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

/** @return list<string> */
function p12ErrorContractViolations(): array
{
    $fixturePath = SourceScanner::repoRoot().'/tests/fixtures/public-error-contract.php';
    $fixture = require $fixturePath;
    $violations = [];

    if (($fixture['schema'] ?? null) !== 'corex-public-error-contract/1') {
        return ['E-FIXTURE-SCHEMA'];
    }

    $expected = [];
    $knownCodes = [];

    foreach ($fixture['contracts'] ?? [] as $row) {
        $missing = array_diff(['owner', 'code', 'category', 'retryable', 'path', 'detail_keys', 'declaration'], array_keys($row));

        if ($missing !== []) {
            $violations[] = 'E-FIXTURE-SHAPE '.implode(',', $missing);

            continue;
        }

        $knownCodes[$row['owner']][$row['code']] = true;

        if (str_contains($row['declaration'], 'literal')) {
            $expected[p12ErrorContractKey($row)] = $row;
        }
    }

    foreach ($fixture['dynamic_adapters'] ?? [] as $adapter) {
        $source = SourceScanner::repoRoot().'/'.$adapter['source'];

        if (! is_file($source) || hash_file('sha256', $source) !== $adapter['sha256']) {
            $violations[] = 'E-DYNAMIC-ADAPTER '.$adapter['source'];
        }
    }

    $actual = p12DeclaredErrorContracts($violations, $fixture['dynamic_adapters'] ?? []);

    foreach ($actual as $key => $row) {
        if (isset($expected[$key])) {
            continue;
        }

        $violations[] = isset($knownCodes[$row['owner']][$row['code']])
            ? 'E-CONTRACT-DRIFT '.$row['owner'].' '.$row['code']
            : 'E-UNREGISTERED-CODE '.$row['owner'].' '.$row['code'];
    }

    foreach (array_diff_key($expected, $actual) as $row) {
        $violations[] = 'E-MISSING-DECLARATION '.$row['owner'].' '.$row['code'];
    }

    sort($violations);

    return array_values(array_unique($violations));
}

/**
 * @param  list<string>  $violations
 * @param  list<array<string, mixed>>  $adapters
 * @return array<string, array<string, mixed>>
 */
function p12DeclaredErrorContracts(array &$violations, array $adapters): array
{
    $contracts = [];
    $dynamicSources = array_fill_keys(array_column($adapters, 'source'), true);

    foreach (['operations' => 'ApiError', 'tools' => 'ApiError', 'webhooks' => 'ApiError', 'custom-fields' => 'ErrorDescriptor'] as $package => $class) {
        $owner = 'axiomasoft/corex-'.(in_array($package, ['tools', 'webhooks'], true) ? 'operations' : $package);
        $directory = SourceScanner::packagePath($package, 'src');

        foreach (SourceScanner::filesMatching($directory, '/\bnew\s+(?:\\\\?[A-Za-z_][A-Za-z0-9_\\\\]*)?'.preg_quote($class, '/').'\s*\(/') as $path) {
            $relative = substr($path, strlen(SourceScanner::repoRoot()) + 1);

            foreach (p12ConstructorCalls($path, $class) as $call) {
                $arguments = $call['arguments'];
                $code = p12LiteralString($arguments['code'] ?? $arguments['errorCode'] ?? $arguments[0] ?? '');

                if ($code === null) {
                    if (! isset($dynamicSources[$relative])) {
                        $violations[] = 'E-DYNAMIC-ADAPTER '.$relative.':'.$call['line'];
                    }

                    continue;
                }

                if ($class === 'ApiError') {
                    $status = p12LiteralInt($arguments['status'] ?? $arguments[1] ?? '');
                    $category = p12Category($arguments['category'] ?? '') ?? p12CategoryFromStatus($status);
                } else {
                    $category = p12Category($arguments['category'] ?? $arguments[1] ?? '');
                }

                if ($category === null) {
                    if (! isset($dynamicSources[$relative])) {
                        $violations[] = 'E-DYNAMIC-ADAPTER '.$relative.':'.$call['line'];
                    }

                    continue;
                }

                $retryable = p12LiteralBool($arguments['retryable'] ?? ($class === 'ErrorDescriptor' ? ($arguments[2] ?? 'false') : ($arguments[5] ?? 'false')));
                $pathValue = p12NullableLiteralString($arguments['path'] ?? ($class === 'ErrorDescriptor' ? ($arguments[3] ?? 'null') : ($arguments[6] ?? 'null')));
                $details = $arguments['details'] ?? ($class === 'ErrorDescriptor' ? ($arguments[4] ?? '[]') : ($arguments[3] ?? '[]'));
                $detailKeys = p12DetailKeys($details);

                if ($retryable === null || $pathValue === false) {
                    if (! isset($dynamicSources[$relative])) {
                        $violations[] = 'E-DYNAMIC-ADAPTER '.$relative.':'.$call['line'];
                    }

                    continue;
                }

                $row = [
                    'owner' => $owner,
                    'code' => $code,
                    'category' => $category,
                    'retryable' => $retryable,
                    'path' => $pathValue,
                    'detail_keys' => $detailKeys,
                ];
                $base = implode('|', [$owner, $code, $category, $retryable ? '1' : '0', $pathValue ?? 'null']);

                if (isset($contracts[$base])) {
                    $row['detail_keys'] = in_array('*', [...$contracts[$base]['detail_keys'], ...$detailKeys], true)
                        ? ['*']
                        : array_values(array_unique([...$contracts[$base]['detail_keys'], ...$detailKeys]));
                    sort($row['detail_keys']);
                }

                $contracts[$base] = $row;
            }
        }
    }

    $result = [];

    foreach ($contracts as $row) {
        $result[p12ErrorContractKey($row)] = $row;
    }

    return $result;
}

/**
 * @return list<array{line: int, arguments: array<int|string, string>}>
 */
function p12ConstructorCalls(string $path, string $class): array
{
    $tokens = token_get_all((string) file_get_contents($path));
    $calls = [];
    $count = count($tokens);

    for ($index = 0; $index < $count; $index++) {
        if (! is_array($tokens[$index]) || $tokens[$index][0] !== T_NEW) {
            continue;
        }

        $line = $tokens[$index][2];
        $cursor = $index + 1;

        while ($cursor < $count && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
            $cursor++;
        }

        $name = '';

        while ($cursor < $count && ((is_array($tokens[$cursor]) && in_array($tokens[$cursor][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR], true)) || $tokens[$cursor] === '\\')) {
            $name .= is_array($tokens[$cursor]) ? $tokens[$cursor][1] : $tokens[$cursor];
            $cursor++;
        }

        if (basename(str_replace('\\', '/', $name)) !== $class) {
            continue;
        }

        while ($cursor < $count && p12TokenText($tokens[$cursor]) !== '(') {
            $cursor++;
        }

        if ($cursor >= $count) {
            continue;
        }

        [$rawArguments, $index] = p12BalancedTokens($tokens, $cursor, '(', ')');
        $calls[] = ['line' => $line, 'arguments' => p12Arguments($rawArguments)];
    }

    return $calls;
}

/** @param array<int, array{0: int, 1: string, 2?: int}|string> $tokens */
function p12BalancedTokens(array $tokens, int $start, string $open, string $close): array
{
    $depth = 0;
    $contents = [];

    for ($index = $start; $index < count($tokens); $index++) {
        $text = p12TokenText($tokens[$index]);

        if ($text === $open) {
            $depth++;

            if ($depth === 1) {
                continue;
            }
        } elseif ($text === $close) {
            $depth--;

            if ($depth === 0) {
                return [$contents, $index];
            }
        }

        $contents[] = $tokens[$index];
    }

    return [$contents, count($tokens) - 1];
}

/** @param array<int, array{0: int, 1: string, 2?: int}|string> $tokens */
function p12Arguments(array $tokens): array
{
    $parts = [];
    $current = '';
    $depth = 0;

    foreach ($tokens as $token) {
        $text = p12TokenText($token);

        if (in_array($text, ['(', '[', '{'], true)) {
            $depth++;
        } elseif (in_array($text, [')', ']', '}'], true)) {
            $depth--;
        }

        if ($text === ',' && $depth === 0) {
            $parts[] = trim($current);
            $current = '';

            continue;
        }

        $current .= $text;
    }

    if (trim($current) !== '') {
        $parts[] = trim($current);
    }

    $arguments = [];

    foreach ($parts as $position => $part) {
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*:\s*(.*)$/s', $part, $match) === 1) {
            $arguments[$match[1]] = trim($match[2]);
        } else {
            $arguments[$position] = $part;
        }
    }

    return $arguments;
}

function p12TokenText(array|string $token): string
{
    return is_array($token) ? $token[1] : $token;
}

function p12LiteralString(string $value): ?string
{
    $value = trim($value);

    if (preg_match("/^'((?:\\\\.|[^'])*)'$/s", $value, $match) !== 1) {
        return null;
    }

    return str_replace(['\\\\', "\\'"], ['\\', "'"], $match[1]);
}

function p12NullableLiteralString(string $value): string|null|false
{
    return trim($value) === 'null' ? null : (p12LiteralString($value) ?? false);
}

function p12LiteralInt(string $value): ?int
{
    $value = trim($value);

    return preg_match('/^[0-9]+$/D', $value) === 1 ? (int) $value : null;
}

function p12LiteralBool(string $value): ?bool
{
    return match (trim($value)) {
        'true' => true,
        'false', '' => false,
        default => null,
    };
}

function p12Category(string $value): ?string
{
    if (preg_match('/ErrorCategory::([A-Za-z]+)/', $value, $match) !== 1) {
        return null;
    }

    return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $match[1]));
}

function p12CategoryFromStatus(?int $status): ?string
{
    return match ($status) {
        401 => 'authentication',
        403 => 'authorization',
        404 => 'not_found',
        409 => 'conflict',
        422 => 'validation',
        429 => 'rate_limit',
        503 => 'unavailable',
        500 => 'internal',
        default => $status !== null && $status >= 400 && $status < 500 ? 'validation' : null,
    };
}

/** @return list<string> */
function p12DetailKeys(string $value): array
{
    $value = trim($value);

    if ($value === '[]') {
        return [];
    }

    if (! str_starts_with($value, '[')) {
        return ['*'];
    }

    preg_match_all("/'((?:\\\\.|[^'])+)'\s*=>/", $value, $matches);
    $keys = array_values(array_unique($matches[1]));
    sort($keys);

    return $keys;
}

/** @param array<string, mixed> $row */
function p12ErrorContractKey(array $row): string
{
    $detailKeys = $row['detail_keys'];
    sort($detailKeys);

    return implode('|', [
        $row['owner'],
        $row['code'],
        $row['category'],
        $row['retryable'] ? '1' : '0',
        $row['path'] ?? 'null',
        implode(',', $detailKeys),
    ]);
}
