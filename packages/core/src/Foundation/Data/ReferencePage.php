<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ReferencePage
{
    /** @param list<LifecycleBlocker> $blockers
     * @param list<CleanupAction> $actions */
    public function __construct(public array $blockers, public string $revision, public ?ReferenceCursor $next = null, public array $actions = []) {}
}
