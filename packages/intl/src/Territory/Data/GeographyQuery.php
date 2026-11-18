<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class GeographyQuery
{
    public function __construct(
        public string $kind,
        public ?ReferenceIdentity $countryKey,
        public ?ReferenceIdentity $parentKey,
        public ?ReferenceIdentity $placeKey,
        public ?string $query,
        public string $locale,
        public ?string $snapshotId,
        public string $mode,
        public int $limit,
    ) {}
}
