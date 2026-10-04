<?php

declare(strict_types=1);

namespace CoreX\Config\Internal;

use Illuminate\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use LogicException;

/**
 * Late access to the running application for a long-lived service that must not hold it
 * (`Closure(): Application`): under a resident worker the active container is replaced per
 * request, so the closure resolves it at call time.
 *
 * @internal Composition helper of the kernel bridge.
 */
final class ApplicationLocator
{
    public static function current(): Application
    {
        $application = Container::getInstance();

        if (! $application instanceof Application) {
            throw new LogicException('The active container is not a Laravel application.');
        }

        return $application;
    }
}
