<?php

declare(strict_types=1);

namespace CoreX\Operations\OpenApi\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/** A package-owned document in the installed-code contract snapshot. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ContractSnapshotSection
{
    public function file(): string;

    /** @return array<string, mixed> */
    public function document(string $major): array;
}
