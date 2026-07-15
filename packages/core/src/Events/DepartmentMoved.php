<?php

declare(strict_types=1);

namespace CoreX\Events;

use Override;

/**
 * Domain-event shape for a department reparent (B-10 domain-events table,
 * `core.department.moved`). AzGuard invalidates its scope=dept_tree cache
 * on this event (B-10 §5.7) — the invalidator itself is out of scope here
 * (consumer-side, P2).
 */
final class DepartmentMoved extends DomainEvent
{
    public function __construct(
        public readonly string $departmentId,
        public readonly string $oldPath,
        public readonly string $newPath,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function name(): string
    {
        return 'core.department.moved';
    }
}
