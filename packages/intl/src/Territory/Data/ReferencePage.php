<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

final readonly class ReferencePage
{
    /** @param list<json-map> $records | @param array<string, null|bool|int|string|array> $provenance */
    public function __construct(
        public string $status,
        public array $records,
        public ?ReferenceCursor $next,
        public ?string $snapshotId,
        public array $provenance,
    ) {}
}
