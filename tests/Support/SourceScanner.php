<?php

declare(strict_types=1);

namespace CoreX\Tests\Support;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Filesystem helpers for architecture/layer gates — resolves monorepo
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

        /** @var SplFileInfo $file */
        foreach (self::walk($dir) as $file) {
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

    /**
     * Line-level counterpart of filesMatching(): every line under $dir (in files
     * with one of $extensions) matching $pattern, reported repo-relatively as
     * `path:line: text` so a red gate names the exact offending site.
     *
     * @param  list<string>  $extensions
     * @return list<string>
     */
    public static function linesMatching(string $dir, string $pattern, array $extensions): array
    {
        $matches = [];
        $rootPrefix = self::repoRoot().'/';

        /** @var SplFileInfo $file */
        foreach (self::walk($dir) as $file) {
            if (! \in_array($file->getExtension(), $extensions, strict: true)) {
                continue;
            }

            $lines = explode("\n", (string) file_get_contents($file->getPathname()));
            $relative = str_starts_with($file->getPathname(), $rootPrefix)
                ? substr($file->getPathname(), \strlen($rootPrefix))
                : $file->getPathname();

            foreach ($lines as $index => $line) {
                if (preg_match($pattern, $line) === 1) {
                    $matches[] = $relative.':'.($index + 1).': '.trim($line);
                }
            }
        }

        sort($matches);

        return $matches;
    }

    /**
     * File iterator over $dir with third-party subtrees (`vendor/`,
     * `node_modules/`) pruned — they are installed artifacts, not repo sources,
     * and scanning them would both blow up runtime and produce foreign hits.
     *
     * @return iterable<SplFileInfo>
     */
    private static function walk(string $dir): iterable
    {
        return new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                static fn (SplFileInfo $current): bool => ! $current->isDir()
                    || ! \in_array($current->getFilename(), ['vendor', 'node_modules'], strict: true),
            ),
        );
    }
}
