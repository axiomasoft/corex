<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use Packman\Attributes\Internal;

/** In-process handoff to Laravel's synchronous transaction callback. */
#[Internal]
final readonly class FrozenQueuePayload
{
    public function __construct(public string $json) {}
}
