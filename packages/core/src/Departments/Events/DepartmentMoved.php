<?php

declare(strict_types=1);

namespace CoreX\Departments\Events;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Events\DomainEvent;
use Override;

/**
 * Domain-event shape for a department reparent (`core.department.moved`).
 * AzGuard invalidates its scope=dept_tree cache on this event — the
 * invalidator itself is out of scope here (consumer-side).
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class DepartmentMoved extends DomainEvent
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
