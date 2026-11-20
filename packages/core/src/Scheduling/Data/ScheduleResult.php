<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ScheduleResult
{
    public function __construct(public string $status, public string $requestKey, public ?string $id = null)
    {
        if (! in_array($status, ['created', 'existing', 'conflict'], true)
            || (($status === 'conflict') !== ($id === null)) || $id === '') {
            throw new InvalidArgumentException('Invalid wakeup schedule result.');
        }
    }
}
