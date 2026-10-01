<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
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
