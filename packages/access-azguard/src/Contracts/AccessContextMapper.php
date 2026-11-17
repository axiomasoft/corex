<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard\Contracts;

use AzGuard\Kernel\Identity\AccessScope;
use AzGuard\Kernel\Identity\SubjectRef;
use CoreX\Access\Data\RecordContext;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\Principal;

/** Host identity mapping only; permission decisions remain with AzGuard. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface AccessContextMapper
{
    public function subject(Principal $principal): SubjectRef;

    /** Null means no trusted account context: callers must deny. */
    public function scope(?RecordContext $context = null): ?AccessScope;
}
