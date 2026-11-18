<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use Illuminate\Http\Request;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface EffectiveCapabilities
{
    public function fingerprint(): string;

    /** @return list<array{id: string, enabled: bool, reason: ?string, limits: array<string, int|string>}> */
    public function effective(Request $request): array;
}
