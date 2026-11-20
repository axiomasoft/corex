<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use CoreX\Context\Exceptions\ScopeContextMismatch;
use Illuminate\Contracts\Queue\Job;
use Packman\Attributes\Internal;
use Throwable;

#[Internal]
final readonly class WorkQueueHandler
{
    public function __construct(private WorkQueueBoundary $boundary) {}

    public function fire(Job $job, mixed $data): void
    {
        $this->boundary->invoke($job, static function (object $handler, string $method) use ($job, $data): mixed {
            $call = [$handler, $method];

            if (! is_callable($call)) {
                throw new ScopeContextMismatch('Declared work handler is unavailable.');
            }

            return $call($job, $data);
        });
    }

    public function failed(mixed $data, Throwable $error, string $uuid, Job $job): void
    {
        $this->boundary->invoke($job, static function (object $handler) use ($data, $error, $uuid, $job): void {
            if (method_exists($handler, 'failed')) {
                $handler->failed($data, $error, $uuid, $job);
            }
        });
    }
}
