<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

final readonly class PurgeClaim
{
    public function __construct(
        public int $operationId,
        public string $fileId,
        public int $epoch,
        public int $fileVersion,
        /** @var list<string> */
        public array $objectKeys,
    ) {}
}
