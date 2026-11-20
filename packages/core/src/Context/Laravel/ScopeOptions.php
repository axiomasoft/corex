<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ScopeOptions
{
    /** @param list<string> $tenantJobs
     * @param list<string> $centralJobs */
    public function __construct(public ?string $deploymentId, public ?string $controlConnection, public array $tenantJobs, public array $centralJobs) {}
}
