<?php

declare(strict_types=1);

namespace CoreX\Notifications\Inbox\Data;

final readonly class CancelResult
{
    /** @param list<string> $alreadyDispatched */
    public function __construct(public bool $cancelled, public array $alreadyDispatched) {}
}
