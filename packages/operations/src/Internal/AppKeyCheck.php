<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal;

use Override;
use Packman\Attributes\Internal;
use Packman\Config\Contracts\RuntimeConfigCheck;

/** The idempotency store derives its key hash from `app.key`; production must not start without it. */
#[Internal]
final readonly class AppKeyCheck implements RuntimeConfigCheck
{
    public function __construct(private mixed $appKey) {}

    #[Override]
    public function violations(bool $production): array
    {
        if (! $production || (is_string($this->appKey) && $this->appKey !== '')) {
            return [];
        }

        return ['app.key: must be configured (APP_KEY) for the idempotency store'];
    }
}
