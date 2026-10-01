<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

use CoreX\Attributes\Api;

#[Api(since: '1.0')]
final readonly class DeleteResult
{
    public function __construct(public bool $deleted, public bool $purgePending) {}
}
