<?php

declare(strict_types=1);

/*
 * Standalone package suites install their own vendor/ directory, so they do not see the
 * monorepo's autoload-dev classmap. Requiring this file registers the shared
 * CoreX\Tests\Support fixtures lazily: a package suite can use any of them without
 * keeping a hand-written list of require_once lines in sync with its tests.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'CoreX\\Tests\\Support\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__.'/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

    if (is_file($file)) {
        require_once $file;
    }
});
