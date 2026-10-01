<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Http\Request;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface EffectiveCapabilities
{
    public function fingerprint(): string;

    /** @return list<array{id: string, enabled: bool, reason: ?string, limits: array<string, int|string>}> */
    public function effective(Request $request): array;
}
