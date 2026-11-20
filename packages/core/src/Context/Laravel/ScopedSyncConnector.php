<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use Illuminate\Queue\Connectors\SyncConnector;
use Packman\Attributes\Internal;

#[Internal]
final class ScopedSyncConnector extends SyncConnector
{
    /** @param array<array-key,mixed> $config */
    public function connect(array $config): ScopedSyncQueue
    {
        return new ScopedSyncQueue(($config['after_commit'] ?? false) === true);
    }
}
