<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use Illuminate\Contracts\Container\Container;
use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final class WorkProcessHealth
{
    private bool $poisoned = false;

    public function check(): void
    {
        if ($this->poisoned) {
            throw new RuntimeException('The worker could not restore its previous work scope.');
        }
    }

    public function stop(Container $container): void
    {
        $this->poisoned = true;

        if ($container->resolved('queue.worker')) {
            $worker = $container->make('queue.worker');
            $worker->shouldQuit = true;
        }
    }
}
