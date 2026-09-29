<?php

declare(strict_types=1);

namespace CoreX\Modules\Compiler;

/**
 * Composer name, version and source-or-dist reference.
 *
 * Path, timestamp, hostname, tenant and activation state are not version metadata.
 *
 * @internal compiler input
 */
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
