<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account;

use CoreX\Scheduler\Contracts\WakeupScheduleHint;

/**
 * Negative fixture for a wakeup contract import outside
 * Integration/Wakeup must fail the package-graph allowlist.
 */
final class WakeupForeignContextProbe
{
    public function touch(WakeupScheduleHint $hint): void {}
}
