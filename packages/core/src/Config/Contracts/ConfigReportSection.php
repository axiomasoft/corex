<?php

declare(strict_types=1);

namespace CoreX\Config\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/** A package's contribution to the effective configuration report. */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ConfigReportSection
{
    public function order(): int;

    /** @return array<class-string, list<string>> */
    public function options(): array;

    /** @return list<string> */
    public function secretKeys(): array;

    /** @return array<string, string> */
    public function secretDefaults(): array;

    /** @return list<string> */
    public function drivers(): array;

    /** @return list<array{line: string, error: ?string}> */
    public function adapters(): array;
}
