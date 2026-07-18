<?php

declare(strict_types=1);

namespace CoreX\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Filesystem helpers for architecture/layer gates (P3.5) — resolves monorepo
 * package paths and scans PHP sources for import patterns, independent of the
 * composer autoloader (so gates read raw `use` statements, not resolved classes).
 */
final class SourceScanner
{
    public static function repoRoot(): string
    {
        return \dirname(__DIR__, 2);
    }

    public static function packagePath(string $package, string $relative = ''): string
    {
        $base = self::repoRoot().'/packages/'.$package;

        return $relative === '' ? $base : $base.'/'.$relative;
    }

    /**
     * Absolute paths of every *.php file under $dir whose contents match $pattern.
     *
     * @return list<string>
     */
    public static function filesMatching(string $dir, string $pattern): array
    {
        $matches = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match($pattern, (string) file_get_contents($file->getPathname())) === 1) {
                $matches[] = $file->getPathname();
            }
        }

        sort($matches);

        return $matches;
    }
}
