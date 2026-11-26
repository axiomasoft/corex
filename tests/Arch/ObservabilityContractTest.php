<?php

declare(strict_types=1);

use CoreX\Tests\Support\SourceScanner;

const OBSERVABILITY_METRIC_LABELS = ['package', 'operation', 'outcome', 'category', 'retry_bucket'];

test('observability gate keeps labels bounded and failure payloads redacted', function (): void {
    expect(observabilityViolations())->toBe([]);
});

test('observability gate rejects an identifier metric label', function (): void {
    $path = SourceScanner::packagePath('core', 'src/Observability/Internal/MetricAttributes.php');
    $original = (string) file_get_contents($path);
    $changed = str_replace("        'retry_bucket',", "        'retry_bucket',\n        'account_id',", $original);
    file_put_contents($path, $changed);

    try {
        expect(observabilityViolations())->toContain('O-LABEL-SET account_id');
    } finally {
        file_put_contents($path, $original);
    }
});

test('observability gate rejects an arbitrary OpenTelemetry attribute map', function (): void {
    $path = SourceScanner::packagePath('core', 'src/Observability/UnboundedAttributeMapProbe.php');

    file_put_contents($path, <<<'PHP'
<?php
final class UnboundedAttributeMapProbe
{
    public function record(object $counter): void
    {
        $counter->add(1, ['tenant' => 'probe']);
    }
}
PHP);

    try {
        expect(observabilityViolations())->toContain('O-ARBITRARY-ATTRIBUTES packages/core/src/Observability/UnboundedAttributeMapProbe.php');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

test('observability gate rejects raw Throwable logging', function (): void {
    $path = SourceScanner::packagePath('core', 'src/Observability/ThrowableLogProbe.php');

    file_put_contents($path, <<<'PHP'
<?php
final class ThrowableLogProbe
{
    public function record(object $logger, Throwable $error): void
    {
        $logger->error('probe', ['exception' => $error]);
    }
}
PHP);

    try {
        expect(observabilityViolations())->toContain('O-RAW-THROWABLE packages/core/src/Observability/ThrowableLogProbe.php');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

/** @return list<string> */
function observabilityViolations(): array
{
    $violations = [];
    $metricPath = SourceScanner::packagePath('core', 'src/Observability/Internal/MetricAttributes.php');
    $labels = observabilityStringConstant($metricPath, 'ALLOWED');

    foreach (array_values(array_diff($labels, OBSERVABILITY_METRIC_LABELS)) as $label) {
        $violations[] = 'O-LABEL-SET '.$label;
    }

    foreach (array_values(array_diff(OBSERVABILITY_METRIC_LABELS, $labels)) as $label) {
        $violations[] = 'O-LABEL-MISSING '.$label;
    }

    $directory = SourceScanner::packagePath('core', 'src/Observability');

    foreach (SourceScanner::filesMatching($directory, '/->(?:add|record|setAttribute|setAttributes|debug|info|notice|warning|error|critical|alert|emergency|log)\s*\(/') as $path) {
        $relative = substr($path, strlen(SourceScanner::repoRoot()) + 1);

        foreach (observabilityObjectMethodCalls($path) as $call) {
            if (in_array($call['method'], ['setAttribute', 'setAttributes'], true)
                || (in_array($call['method'], ['add', 'record'], true)
                    && isset($call['arguments'][1])
                    && ! in_array(preg_replace('/\s+/', '', $call['arguments'][1]), ['$attributes->all()', '[]'], true))) {
                $violations[] = 'O-ARBITRARY-ATTRIBUTES '.$relative;
            }

            if (in_array($call['method'], ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency', 'log'], true)
                && preg_match('/(?:\$error\b|\$exception\b|[\'\"]exception[\'\"]\s*=>)/', implode(' ', $call['arguments'])) === 1) {
                $violations[] = 'O-RAW-THROWABLE '.$relative;
            }
        }
    }

    sort($violations);

    return array_values(array_unique($violations));
}

/** @return list<string> */
function observabilityStringConstant(string $path, string $constant): array
{
    $tokens = token_get_all((string) file_get_contents($path));
    $count = count($tokens);

    for ($index = 0; $index < $count; $index++) {
        if (! is_array($tokens[$index]) || $tokens[$index][0] !== T_CONST) {
            continue;
        }

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            if (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_STRING && $tokens[$cursor][1] === $constant) {
                while ($cursor < $count && observabilityTokenText($tokens[$cursor]) !== '[') {
                    $cursor++;
                }

                $strings = [];
                $depth = 0;

                for (; $cursor < $count; $cursor++) {
                    $text = observabilityTokenText($tokens[$cursor]);

                    if ($text === '[') {
                        $depth++;
                    } elseif ($text === ']') {
                        $depth--;

                        if ($depth === 0) {
                            return $strings;
                        }
                    } elseif (is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_CONSTANT_ENCAPSED_STRING) {
                        $strings[] = trim($tokens[$cursor][1], "'\"");
                    }
                }
            }

            if (observabilityTokenText($tokens[$cursor]) === ';') {
                break;
            }
        }
    }

    return [];
}

/** @return list<array{method: string, arguments: list<string>}> */
function observabilityObjectMethodCalls(string $path): array
{
    $tokens = token_get_all((string) file_get_contents($path));
    $calls = [];

    for ($index = 0; $index < count($tokens) - 2; $index++) {
        if (! is_array($tokens[$index]) || $tokens[$index][0] !== T_OBJECT_OPERATOR) {
            continue;
        }

        $cursor = $index + 1;

        while (isset($tokens[$cursor]) && is_array($tokens[$cursor]) && $tokens[$cursor][0] === T_WHITESPACE) {
            $cursor++;
        }

        if (! isset($tokens[$cursor]) || ! is_array($tokens[$cursor]) || $tokens[$cursor][0] !== T_STRING) {
            continue;
        }

        $method = $tokens[$cursor][1];

        while (isset($tokens[$cursor]) && observabilityTokenText($tokens[$cursor]) !== '(') {
            $cursor++;
        }

        if (! isset($tokens[$cursor])) {
            continue;
        }

        [$arguments, $index] = observabilityBalanced($tokens, $cursor);
        $calls[] = ['method' => $method, 'arguments' => observabilityArguments($arguments)];
    }

    return $calls;
}

/** @param array<int, array{0: int, 1: string, 2?: int}|string> $tokens */
function observabilityBalanced(array $tokens, int $start): array
{
    $depth = 0;
    $contents = [];

    for ($index = $start; $index < count($tokens); $index++) {
        $text = observabilityTokenText($tokens[$index]);

        if ($text === '(') {
            $depth++;

            if ($depth === 1) {
                continue;
            }
        } elseif ($text === ')') {
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
function observabilityArguments(array $tokens): array
{
    $parts = [];
    $current = '';
    $depth = 0;

    foreach ($tokens as $token) {
        $text = observabilityTokenText($token);

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

    return $parts;
}

function observabilityTokenText(array|string $token): string
{
    return is_array($token) ? $token[1] : $token;
}
