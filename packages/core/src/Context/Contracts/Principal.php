<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Enums\PrincipalType;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * The single authenticated-principal concept of the platform: a user, an AI
 * agent or an API key, identified by (type, id). Access checks grants against
 * it and operation handlers receive it as the caller (RequestContext).
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface Principal
{
    public function principalType(): PrincipalType;

    public function principalId(): string;
}
