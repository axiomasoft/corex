<?php

declare(strict_types=1);

final class SelfCheck
{
    public const EXPECTED = [
        'src_php_files' => 888,
        'config_lines' => 153,
        'static_dollar_lines' => 0,
        'internal_directories' => 19,
        'internal_files' => 169,
        'migration_files' => 86,
    ];

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{ok: bool, expected: array<string, int>, observed: array<string, int>, deltas: array<string, int>}
     */
    public static function compare(string $repoRoot, array $rows): array
    {
        $observed = [
            'src_php_files' => self::countFiles($repoRoot.'/packages', '*/src', '*.php'),
            'config_lines' => self::countLinesMatching($repoRoot.'/packages', '*/src', '*.php', '/\bconfig\s*\(/'),
            'static_dollar_lines' => self::countLinesMatching($repoRoot.'/packages', '*/src', '*.php', '/\bstatic\s+\$/'),
            'internal_directories' => self::countInternalDirs($repoRoot),
            'internal_files' => self::countFiles($repoRoot.'/packages', '*/src', 'Internal/**/*.php'),
            'migration_files' => self::countFiles($repoRoot.'/packages', '*/database/migrations', '*.php'),
        ];

        $deltas = [];
        foreach (self::EXPECTED as $key => $expected) {
            $deltas[$key] = $observed[$key] - $expected;
        }

        return [
            'schema' => 'inventory-self-check/1',
            'ok' => array_values($deltas) === array_fill(0, count($deltas), 0),
            'expected' => self::EXPECTED,
            'observed' => $observed,
            'deltas' => $deltas,
            'row_count' => count($rows),
        ];
    }

    public static function format(array $check): string
    {
        $lines = [];
        foreach ($check['expected'] as $key => $expected) {
            $obs = $check['observed'][$key];
            $delta = $check['deltas'][$key];
            $mark = $delta === 0 ? 'OK' : 'FAIL';
            $lines[] = sprintf("%s %s expected=%d observed=%d delta=%+d\n", $mark, $key, $expected, $obs, $delta);
        }

        return implode('', $lines);
    }

    private static function countFiles(string $root, string $middle, string $name): int
    {
        $count = 0;
        foreach (glob($root.'/'.$middle, GLOB_ONLYDIR) ?: [] as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($it as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                if ($name === 'Internal/**/*.php' && ! str_contains($file->getPathname(), '/Internal/')) {
                    continue;
                }
                $count++;
            }
        }

        return $count;
    }

    private static function countLinesMatching(string $root, string $middle, string $name, string $pattern): int
    {
        $count = 0;
        foreach (glob($root.'/'.$middle, GLOB_ONLYDIR) ?: [] as $dir) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($it as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }
                $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);

                if ($lines === false) {
                    continue;
                }
                foreach ($lines as $line) {
                    if (preg_match($pattern, $line) === 1) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    private static function countInternalDirs(string $repoRoot): int
    {
        $dirs = [];
        foreach (glob($repoRoot.'/packages/*/src', GLOB_ONLYDIR) ?: [] as $src) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($src),
                RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($it as $file) {
                if ($file->isDir() && $file->getFilename() === 'Internal') {
                    $dirs[$file->getPathname()] = true;
                }
            }
        }

        return count($dirs);
    }
}
