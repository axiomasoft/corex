<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Enums\PrincipalType;

/**
 * The single authenticated-principal concept of the platform: a user, an AI
 * agent or an API key, identified by (type, id). Access checks grants against
 * it and operation handlers receive it as the caller (RequestContext).
 * Moved from axiomasoft/corex-access (audit 2026-10-08 P-02); the former
 * CoreX\Access\Contracts\Principal is a deprecated alias of this type.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface Principal
{
    public function principalType(): PrincipalType;

    public function principalId(): string;
}
