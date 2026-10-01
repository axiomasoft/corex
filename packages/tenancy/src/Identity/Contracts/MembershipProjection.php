<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Identity\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface MembershipProjection
{
    public function refresh(string $identityId, string $accountId, string $product, string $requestId): void;
}
