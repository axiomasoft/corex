<?php

declare(strict_types=1);

namespace CoreX\Testing;

use Illuminate\Database\Events\MigrationStarted;

final class LifecycleStringMigrationListener
{
    public static int $calls = 0;

    public function onStarted(MigrationStarted $event): void
    {
        self::$calls++;
    }
}
