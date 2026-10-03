#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Configuration reference generator (P05.4, D69 p.9).
 *
 * Writes (`--write`) or verifies byte-for-byte (`--check`) docs/architecture/configuration.md.
 *
 * Source of the rows:
 *  - key set, type and default: the config files packages/<name>/config/*.php, read statically
 *    (nikic/php-parser; nothing is executed, so no environment, host path or date leaks in);
 *  - typed_options_field: `<ShortOptionsClass>::$property`, resolved by reflection over the
 *    Options classes declared per config file in scripts/configuration-classes.json;
 *  - owner, secret, mode, reloadable, validation, deprecated_aliases, notes: carried from the
 *    same JSON file, which is the only place a human edits.
 *
 * A package config key without an Options field, a config key without a carried row, a carried
 * row without a config key (outside `external`) and an unreadable Options class are generator
 * errors (exit 2); `--check` drift is exit 1.
 */

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\ParserFactory;

const EXIT_OK = 0;
const EXIT_DRIFT = 1;
const EXIT_ERROR = 2;

const COLUMNS = ['key', 'owner', 'type', 'default', 'secret', 'mode', 'reloadable', 'validation', 'typed_options_field', 'deprecated_aliases', 'notes'];

$repoRoot = dirname(__DIR__);
require $repoRoot.'/vendor/autoload.php';

$mode = $argv[1] ?? '';

if (! in_array($mode, ['--write', '--check'], true)) {
    fwrite(STDERR, "usage: php scripts/configuration-reference.php --write|--check\n");
    exit(EXIT_ERROR);
}

try {
    $document = renderDocument($repoRoot);
} catch (RuntimeException $exception) {
    fwrite(STDERR, 'configuration-reference: '.$exception->getMessage()."\n");
    exit(EXIT_ERROR);
}

$target = $repoRoot.'/docs/architecture/configuration.md';

if ($mode === '--write') {
    file_put_contents($target, $document);
    fwrite(STDOUT, "configuration-reference: written\n");
    exit(EXIT_OK);
}

$current = is_file($target) ? (string) file_get_contents($target) : '';

if ($current === $document) {
    fwrite(STDOUT, "configuration-reference: OK\n");
    exit(EXIT_OK);
}

$expected = explode("\n", $document);
$actual = explode("\n", $current);

foreach ($expected as $index => $line) {
    if (($actual[$index] ?? null) !== $line) {
        fwrite(STDERR, sprintf("configuration-reference: docs/architecture/configuration.md differs at line %d\n  expected: %s\n  actual:   %s\n", $index + 1, $line, $actual[$index] ?? '<missing>'));

        break;
    }
}

fwrite(STDERR, "configuration-reference: run `php scripts/configuration-reference.php --write`\n");
exit(EXIT_DRIFT);

/**
 * @return array{
 *     rows: array<string, array<string, string>>,
 *     external: array<string, array<string, string>>,
 *     options: array<string, list<array{class: class-string, prefix?: string}>>,
 *     options_field_overrides: array<string, string>
 * }
 */
function loadClasses(string $path): array
{
    $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    foreach (['rows', 'external', 'options', 'options_field_overrides'] as $section) {
        if (! is_array($decoded) || ! is_array($decoded[$section] ?? null)) {
            throw new RuntimeException("scripts/configuration-classes.json has no `{$section}` section");
        }
    }

    /** @var array{rows: array<string, array<string, string>>, external: array<string, array<string, string>>, options: array<string, list<array{class: class-string, prefix?: string}>>, options_field_overrides: array<string, string>} $decoded */
    return $decoded;
}

function renderDocument(string $repoRoot): string
{
    $classes = loadClasses($repoRoot.'/scripts/configuration-classes.json');
    $parser = (new ParserFactory)->createForNewestSupportedVersion();

    $rows = [];

    $files = glob($repoRoot.'/packages/*/config/*.php');
    $files = $files === false ? [] : $files;
    sort($files);

    foreach ($files as $file) {
        $name = basename($file, '.php');
        $tree = $parser->parse((string) file_get_contents($file)) ?? [];
        $array = null;

        foreach ($tree as $statement) {
            if ($statement instanceof Node\Stmt\Return_ && $statement->expr instanceof Expr\Array_) {
                $array = $statement->expr;
            }
        }

        if ($array === null) {
            throw new RuntimeException(basename(dirname($file, 2)).'/config/'.basename($file).' does not return an array literal');
        }

        $leaves = [];
        flattenArray($array, $name, $leaves);

        foreach ($leaves as $key => $leaf) {
            $rows[$key] = ['source' => $name] + $leaf;
        }
    }

    $errors = [];
    $output = [];

    foreach ($rows as $key => $leaf) {
        $carried = $classes['rows'][$key] ?? null;

        if ($carried === null) {
            $errors[] = "config key `{$key}` has no carried row in scripts/configuration-classes.json";

            continue;
        }

        $field = resolveOptionsField($key, $leaf['source'], $classes, $errors);

        $secret = $carried['secret'] === 'yes';
        $default = $leaf['default'];

        if ($secret && $default !== 'unobserved' && $default !== 'null' && $default !== "''") {
            $default = '***';
        }

        $output[$key] = [$key, $carried['owner'], $leaf['type'], $default, $carried['secret'], $carried['mode'], $carried['reloadable'], $carried['validation'], $field, $carried['deprecated_aliases'], $carried['notes']];
    }

    foreach (array_keys($classes['rows']) as $key) {
        if (! isset($rows[$key])) {
            $errors[] = "carried row `{$key}` has no key in any packages/*/config file (move it to `external` or delete it)";
        }
    }

    foreach ($classes['external'] as $key => $carried) {
        if (isset($rows[$key])) {
            $errors[] = "external row `{$key}` duplicates a package config key";

            continue;
        }

        $field = '—';

        if (isset($carried['options_field'])) {
            $file = explode('.', $key)[0];
            $classes['options_field_overrides'][$key] = $carried['options_field'];
            $field = resolveOptionsField($key, $file, $classes, $errors);
        }

        $output[$key] = [$key, $carried['owner'], 'unobserved', 'unobserved', $carried['secret'], $carried['mode'], $carried['reloadable'], $carried['validation'], $field, $carried['deprecated_aliases'], $carried['notes']];
    }

    if ($errors !== []) {
        throw new RuntimeException("\n  ".implode("\n  ", $errors));
    }

    ksort($output, SORT_STRING);

    $lines = [
        '# Реестр конфигурации',
        '',
        'Инварианты — статьи `CFG-001` … `CFG-006`. Процедура — `config-policy.md`.',
        'Страница генерируется: `php scripts/configuration-reference.php --write`, сверка — `--check`',
        '(composer `configuration-reference`, шаг CI). Набор ключей, тип и значение по умолчанию',
        'читаются из `packages/*/config/*.php`; `typed_options_field` — свойство Options-класса,',
        'которое принимает ключ; остальные колонки хранятся в `scripts/configuration-classes.json`.',
        'Ключ пакетного config без поля Options — ошибка генератора.',
        '',
        '## Каталог ключей',
        '',
        '| '.implode(' | ', COLUMNS).' |',
        '|'.implode('|', array_fill(0, count(COLUMNS), ':--')).'|',
    ];

    foreach ($output as $row) {
        $lines[] = '| '.implode(' | ', array_map(static fn (string $cell): string => str_replace('|', '\\|', $cell), $row)).' |';
    }

    return implode("\n", $lines)."\n";
}

/**
 * @param  array<string, array{type: string, default: string}>  $leaves
 */
function flattenArray(Expr\Array_ $array, string $path, array &$leaves): void
{
    $associative = $array->items !== [];

    foreach ($array->items as $item) {
        if ($item->key === null) {
            $associative = false;
        }
    }

    if (! $associative) {
        $leaves[$path] = ['type' => 'array', 'default' => $array->items === [] ? '[]' : 'unobserved'];

        return;
    }

    foreach ($array->items as $item) {
        $literal = literalOf($item->key);
        $segment = $literal['ok'] && (is_string($literal['value']) || is_int($literal['value'])) ? (string) $literal['value'] : '?';

        if ($item->value instanceof Expr\Array_) {
            flattenArray($item->value, $path.'.'.$segment, $leaves);

            continue;
        }

        $leaves[$path.'.'.$segment] = describeScalar($item->value);
    }
}

/** @return array{ok: bool, value: mixed} */
function literalOf(?Expr $expression): array
{
    if ($expression instanceof Scalar\String_ || $expression instanceof Scalar\Int_ || $expression instanceof Scalar\Float_) {
        return ['ok' => true, 'value' => $expression->value];
    }

    if ($expression instanceof Expr\UnaryMinus) {
        $inner = literalOf($expression->expr);

        return $inner['ok'] && (is_int($inner['value']) || is_float($inner['value'])) ? ['ok' => true, 'value' => -$inner['value']] : ['ok' => false, 'value' => null];
    }

    if ($expression instanceof Expr\ConstFetch) {
        return match (strtolower($expression->name->toString())) {
            'true' => ['ok' => true, 'value' => true],
            'false' => ['ok' => true, 'value' => false],
            'null' => ['ok' => true, 'value' => null],
            default => ['ok' => false, 'value' => null],
        };
    }

    return ['ok' => false, 'value' => null];
}

/** @return array{type: string, default: string} */
function describeScalar(Expr $expression): array
{
    $literal = literalOf($expression);

    if ($literal['ok']) {
        $value = $literal['value'];

        return match (true) {
            is_bool($value) => ['type' => 'bool', 'default' => $value ? 'true' : 'false'],
            is_int($value) => ['type' => 'int', 'default' => (string) $value],
            is_float($value) => ['type' => 'float', 'default' => var_export($value, true)],
            is_string($value) => ['type' => 'string', 'default' => $value === '' ? "''" : $value],
            default => ['type' => 'null', 'default' => 'null'],
        };
    }

    if ($expression instanceof Expr\ClassConstFetch && $expression->class instanceof Node\Name && $expression->name instanceof Node\Identifier && $expression->name->toString() !== 'class') {
        return ['type' => 'const', 'default' => $expression->class->getLast().'::'.$expression->name->toString()];
    }

    return ['type' => 'unobserved', 'default' => 'unobserved'];
}

/**
 * @param  array{rows: array<string, array<string, string>>, external: array<string, array<string, string>>, options: array<string, list<array{class: class-string, prefix?: string}>>, options_field_overrides: array<string, string>}  $classes
 * @param  list<string>  $errors
 */
function resolveOptionsField(string $key, string $file, array $classes, array &$errors): string
{
    $declared = $classes['options'][$file] ?? [];

    if ($declared === []) {
        $errors[] = "config file `{$file}` declares no Options class in scripts/configuration-classes.json";

        return '';
    }

    $override = $classes['options_field_overrides'][$key] ?? null;
    $path = explode('.', substr($key, strlen($file) + 1));

    $candidates = [];

    foreach ($declared as $entry) {
        $prefix = $entry['prefix'] ?? null;

        if ($prefix !== null && $path[0] !== $prefix) {
            continue;
        }

        $candidates[] = [$entry['class'], $prefix === null ? $path : array_slice($path, 1)];
    }

    foreach ($candidates as [$class, $segments]) {
        if (! class_exists($class)) {
            $errors[] = "Options class `{$class}` of config file `{$file}` cannot be loaded";

            return '';
        }

        $properties = array_map(static fn (ReflectionProperty $property): string => $property->getName(), (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC));
        $short = (new ReflectionClass($class))->getShortName();

        if ($override !== null) {
            // `$(reference)`: a documented, non-runtime key (a Composer pin); `$(registrar-read)`: a key read
            // by a registrar at composition time that the Options class does not carry.
            $named = substr($override, strlen($short) + 3);

            if (str_starts_with($override, $short.'::$') && (in_array($named, ['(reference)', '(registrar-read)'], true) || in_array($named, $properties, true))) {
                return $override;
            }

            continue;
        }

        for ($length = count($segments); $length >= 1; $length--) {
            $name = lcfirst(implode('', array_map(static fn (string $segment): string => ucfirst(camelize($segment)), array_slice($segments, 0, $length))));

            if (in_array($name, $properties, true)) {
                return $short.'::$'.$name;
            }
        }
    }

    $errors[] = $override === null
        ? "config key `{$key}` has no Options field (add the property to the Options class or an options_field_overrides entry)"
        : "options_field_overrides `{$key}` => `{$override}` names no public property of the declared Options classes";

    return '';
}

function camelize(string $segment): string
{
    return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $segment))));
}
