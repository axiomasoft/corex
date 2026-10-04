<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use Closure;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use Illuminate\Contracts\Config\Repository;

/**
 * The single seam through which the tenant runtime reads and switches the framework's own runtime
 * configuration (`database.connections.*`, `cache.stores.*`, `session.*`) — values the host owns
 * and the bootstrappers must change per account. The repository is resolved by the composition
 * root on every access, never captured. Package settings never travel through here: those arrive
 * as typed {@see TenancyOptions}.
 */
final readonly class FrameworkConfig
{
    /** @param Closure(): Repository $repository */
    public function __construct(private Closure $repository) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return ($this->repository)()->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        ($this->repository)()->set($key, $value);
    }
}
