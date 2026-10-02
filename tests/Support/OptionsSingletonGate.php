<?php

declare(strict_types=1);

namespace CoreX\Tests\Support;

use Illuminate\Contracts\Foundation\Application;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/** D69 p.3: every `*Options` resolves to one instance; `bind`/`instance` bindings of Options are forbidden. */
final class OptionsSingletonGate
{
    /** @return list<class-string> Options classes declared under `packages/<pkg>/src`. */
    public static function optionsClasses(string $repoRoot): array
    {
        $classes = [];

        foreach (self::sourceRoots($repoRoot) as $source) {
            foreach (self::phpFiles($source) as $file) {
                if (! str_ends_with($file, 'Options.php')) {
                    continue;
                }

                $code = (string) file_get_contents($file);

                if (preg_match('/^namespace\s+([^;]+);/m', $code, $namespace) !== 1) {
                    continue;
                }

                $class = $namespace[1].'\\'.basename($file, '.php');

                if (class_exists($class)) {
                    $classes[] = $class;
                }
            }
        }

        sort($classes);

        return $classes;
    }

    /**
     * @param  list<class-string>  $classes
     * @return list<string> findings of classes that are not shared instances
     */
    public static function runtimeViolations(Application $app, array $classes): array
    {
        $violations = [];

        foreach ($classes as $class) {
            try {
                $same = $app->make($class) === $app->make($class);
            } catch (Throwable $exception) {
                $violations[] = $class.' cannot be resolved: '.$exception->getMessage();

                continue;
            }

            if (! $same) {
                $violations[] = $class.' is rebuilt on every make()';
            }
        }

        return $violations;
    }

    /** @return list<string> findings of `bind`/`instance` registrations of Options in package sources */
    public static function bindingViolations(string $repoRoot): array
    {
        $violations = [];

        foreach (self::sourceRoots($repoRoot) as $source) {
            foreach (self::phpFiles($source) as $file) {
                $lines = file($file);

                if ($lines === false) {
                    continue;
                }

                foreach ($lines as $index => $line) {
                    if (preg_match('/->(?:bind|bindIf|instance)\(\s*\\\\?[\w\\\\]*Options::class/', $line) === 1) {
                        $violations[] = substr($file, strlen($repoRoot) + 1).':'.($index + 1).' binds Options without a lazy singleton';
                    }
                }
            }
        }

        return $violations;
    }

    /** @return list<string> */
    private static function sourceRoots(string $repoRoot): array
    {
        $roots = glob($repoRoot.'/packages/*/src', GLOB_ONLYDIR);

        return $roots === false ? [] : $roots;
    }

    /** @return list<string> */
    private static function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
