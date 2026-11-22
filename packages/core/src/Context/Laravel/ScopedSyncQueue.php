<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use Closure;
use Illuminate\Queue\SyncQueue;
use Packman\Attributes\Internal;

/** Freeze work while its producer scope is current, including before deferred synchronous execution. */
#[Internal]
final class ScopedSyncQueue extends SyncQueue
{
    /**
     * @param  object|string  $job
     * @param  ?string  $queue
     */
    public function push($job, $data = '', $queue = null)
    {
        $prepared = new FrozenQueuePayload(parent::createPayload($job, $queue ?? '', $data));

        return $this->pushFrozen(push: parent::push(...), job: $job, payload: $prepared, queue: $queue);
    }

    /** Illuminate's Queue::push supports object jobs. SyncQueue's PHPDoc is narrower. */
    private function pushFrozen(Closure $push, object|string $job, FrozenQueuePayload $payload, ?string $queue): mixed
    {
        return $push($job, $payload, $queue);
    }

    protected function createPayload($job, $queue, $data = '', $delay = null)
    {
        return $data instanceof FrozenQueuePayload ? $data->json : parent::createPayload($job, $queue, $data, $delay);
    }
}
