<?php

declare(strict_types=1);

namespace CoreX\Media\Internal\Data;

use CoreX\Data\Data\DataScope;
use Packman\Attributes\Internal;

#[Internal]
final readonly class PurgeClaim
{
    public function __construct(
        public int $operationId,
        public string $fileId,
        public int $epoch,
        public int $fileVersion,
        public DataScope $scope,
    ) {}
}
