<?php

declare(strict_types=1);

namespace CoreX\Operations\OpenApi\Contracts;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Call port for the methods consumed across the owning module boundary. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
interface OpenApiDocuments
{
    /**
     * $serverUrl includes the application's chosen mount prefix and API version.
     *
     * @return array<string, mixed>
     */
    public function document(string $version, string $serverUrl = ''): array;
}
