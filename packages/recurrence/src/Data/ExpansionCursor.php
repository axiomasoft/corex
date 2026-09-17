<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Data;

use InvalidArgumentException;

final readonly class ExpansionCursor
{
    /**
     * @param  array<string, null|bool|int|string|array<int|string, mixed>>  $resumePosition
     */
    public function __construct(
        public string $setHash,
        public string $windowHash,
        public string $tzdbVersion,
        public string $libraryVersion,
        public array $resumePosition,
    ) {
        foreach ([$setHash, $windowHash] as $hash) {
            if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
                throw new InvalidArgumentException('The recurrence cursor hash is invalid.');
            }
        }

        if ($tzdbVersion === '' || $libraryVersion === '') {
            throw new InvalidArgumentException('The recurrence cursor runtime binding is invalid.');
        }
    }
}
