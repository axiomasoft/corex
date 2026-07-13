<?php

declare(strict_types=1);

namespace Flex\Modules\Contracts;

use Flex\Modules\Enums\DisableReason;
use Flex\Modules\Exceptions\HasEnabledDependentsException;
use Flex\Modules\Exceptions\MissingDependencyException;
use Flex\Modules\ModuleStatus;

/**
 * State-machine transitions for one module in the current tenant's database
 * (B-10 §3.3/§5.1): available → enabled → disabled → (archived | purged).
 * `enabled` never goes straight to `archived`/`purged` — disable is a
 * mandatory two-step safety gate (B-10 §7.4 п.3).
 */
interface ModuleLifecycle
{
    /**
     * available|disabled|archived → enabled. `$cascade` auto-enables the
     * requires chain instead of throwing {@see MissingDependencyException}.
     */
    public function enable(string $module, string $edition = 'standard', bool $cascade = false): void;

    /**
     * enabled → disabled. Data is NEVER touched. Throws
     * {@see HasEnabledDependentsException} unless `$cascade` disables
     * dependents first.
     */
    public function disable(string $module, DisableReason $reason, bool $cascade = false): void;

    /**
     * disabled|archived → archived ($keepData=true, tables kept) |
     * purged ($keepData=false, drop by mod_records + PurgeHook). Direct
     * uninstall from `enabled` is rejected — disable first.
     */
    public function uninstall(string $module, bool $keepData = true): void;

    /** Catch-up migrations: dbVersion → codeVersion. */
    public function upgrade(string $module): void;

    public function status(string $module): ModuleStatus;
}
