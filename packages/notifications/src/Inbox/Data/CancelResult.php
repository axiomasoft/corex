<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class CancelResult
{
    /** @param list<string> $alreadyDispatched */
    public function __construct(public bool $cancelled, public array $alreadyDispatched) {}
}
