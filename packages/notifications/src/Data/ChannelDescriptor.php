<?php

declare(strict_types=1);

namespace CoreX\Notifications\Data;

final readonly class ChannelDescriptor
{
    public function __construct(public string $key, public string $version, public bool $supportsIdempotency, public bool $supportsReconciliation, public ?int $dedupWindowSeconds, public string $dedupScope) {}
}
