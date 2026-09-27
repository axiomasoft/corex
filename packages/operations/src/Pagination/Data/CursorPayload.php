<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

/** Signed continuation values. Field names and SQL direction stay in the ordering registry. */
final readonly class CursorPayload
{
    /**
     * @param  list<string|int|float|bool|null>  $values
     */
    public function __construct(
        public int $formatVersion,
        public string $ordering,
        public string $direction,
        public array $values,
        public string $scopeHash,
        public int $issuedAt,
        public int $expiresAt,
        public string $keyId,
    ) {}
}
