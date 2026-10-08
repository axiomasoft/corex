<?php

declare(strict_types=1);

/*
 * P02.3 literal pass for PHP files: rewrites old FQCNs, moved paths, renamed directories and composer names
 * inside string literals, heredocs, comments and inline HTML only. Code tokens belong to Rector
 * RenameClassRector; touching them here would rename a reference twice.
 *
 * Usage: php p02-literal-pass.php <rules.json> <file-list.txt>   (rules written by p02-transfer.py)
 */

[$_, $rulesPath, $listPath] = $argv + [null, null, null];
$rules = json_decode((string) file_get_contents((string) $rulesPath), true, flags: JSON_THROW_ON_ERROR);
$files = array_filter(array_map('trim', file((string) $listPath) ?: []));

$literal = [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML];
$changed = 0;

foreach ($files as $file) {
    $source = (string) file_get_contents($file);
    $out = '';
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], $literal, true)) {
            $out .= rewrite($token[1], $rules);

            continue;
        }
        $out .= is_array($token) ? $token[1] : $token;
    }

    if ($out !== $source) {
        file_put_contents($file, $out);
        $changed++;
    }
}

echo "{$changed} php files\n";

/** Mirrors rewrite_text() in p02-transfer.py; keep both in step. */
function rewrite(string $text, array $rules): string
{
    $text = (string) preg_replace_callback(
        '/(?<![\w\\\\])((?:\\\\\\\\|\\\\)?(?:CoreX|Flex)(?:(?:\\\\\\\\|\\\\)[A-Za-z_]\w*)+)/',
        static function (array $m) use ($rules): string {
            $token = $m[1];
            $doubled = str_contains($token, '\\\\');
            $lead = $doubled ? str_starts_with($token, '\\\\') : str_starts_with($token, '\\');
            $bare = ltrim(str_replace('\\\\', '\\', $token), '\\');
            $new = $rules['classes'][$bare] ?? null;

            if ($new === null) {
                return $token;
            }
            $new = $doubled ? str_replace('\\', '\\\\', $new) : $new;

            return $lead ? ($doubled ? '\\\\' : '\\').$new : $new;
        },
        $text,
    );

    foreach ([['paths', '(?<![\w.-])', '(?![\w/])'], ['dirs', '(?<![\w.-])', '(?=/|\b)'], ['names', '(?<![\w./@-])', '(?![\w-])']] as [$kind, $before, $after]) {
        $table = $rules[$kind];

        if ($table === []) {
            continue;
        }
        $text = (string) preg_replace_callback(alternation($kind, $table, $before, $after), static fn (array $m): string => $table[$m[1]], $text);
    }

    return $text;
}

/** One compiled pattern per rule table, longest alternative first (same as the Python side). */
function alternation(string $kind, array $table, string $before, string $after): string
{
    static $cache = [];

    if (! isset($cache[$kind])) {
        $keys = array_keys($table);
        usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));
        $cache[$kind] = '~'.$before.'('.implode('|', array_map(static fn (string $k): string => preg_quote($k, '~'), $keys)).')'.$after.'~';
    }

    return $cache[$kind];
}
