<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Identity\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
interface MembershipProjection
{
    public function refresh(string $identityId, string $accountId, string $product, string $requestId): void;
}
