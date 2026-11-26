<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use CoreX\Access\Laravel\AuthOptions;
use CoreX\Context\Laravel\WorkspaceScopeProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Scope;
use InvalidArgumentException;
use Packman\Attributes\Internal;

/**
 * The one place that decides which workspace global scope access wires and
 * lifts (fail-closed, replaces the silent `class_exists` skip):
 *
 * 1. `corex-access.workspace_scope_class` set ⇒ that class, and it MUST exist
 *    (a typo or a missing tenancy package is a boot-time error, never an
 *    unscoped install);
 * 2. otherwise the kernel {@see WorkspaceScopeProvider} (bound by tenancy);
 * 3. otherwise explicitly none — boxed profile without workspaces.
 *
 * @internal
 */
#[Internal]
final readonly class EffectiveWorkspaceScope
{
    /** @param  class-string|null  $class */
    public function __construct(public ?string $class) {}

    public static function resolve(AuthOptions $options, Container $container): self
    {
        $configured = $options->workspaceScopeClass;

        if ($configured !== null) {
            if (! class_exists($configured)) {
                throw new InvalidArgumentException("corex-access.workspace_scope_class [{$configured}] does not exist; unset it to use the tenancy-provided scope.");
            }

            return self::validated($configured);
        }

        if ($container->bound(WorkspaceScopeProvider::class)) {
            return self::validated($container->make(WorkspaceScopeProvider::class)->scopeClass());
        }

        return new self(null);
    }

    /** @param class-string $class */
    private static function validated(string $class): self
    {
        if (! is_a($class, Scope::class, true)) {
            throw new InvalidArgumentException("Workspace scope [{$class}] must implement the Eloquent Scope contract.");
        }

        return new self($class);
    }
}
