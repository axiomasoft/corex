<?php

declare(strict_types=1);

namespace CoreX\Modules\Data;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/**
 * Composer name, version and source-or-dist reference.
 *
 * Path, timestamp, hostname, tenant and activation state are not version metadata.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class InstalledPackageVersion
{
    public function __construct(
        public string $name,
        public string $version,
        public ?string $reference,
    ) {}

    /** @return array{name: string, version: string, reference: ?string} */
    public function canonical(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'reference' => $this->reference,
        ];
    }
}
