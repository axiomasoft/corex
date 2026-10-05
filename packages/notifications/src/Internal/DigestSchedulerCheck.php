<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal;

use CoreX\Config\Contracts\RuntimeConfigCheck;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** @internal */
final readonly class DigestSchedulerCheck implements RuntimeConfigCheck
{
    public function __construct(
        private NotificationsOptions $options,
        private Application $app,
    ) {}

    #[Override]
    public function violations(bool $production): array
    {
        if (! $this->options->digestEnabled || $this->app->bound(WakeupScheduler::class)) {
            return [];
        }

        return ['corex-notifications.digest.enabled requires a bound CoreX\\Scheduling\\Contracts\\WakeupScheduler'];
    }
}
