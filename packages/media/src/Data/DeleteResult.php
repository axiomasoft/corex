<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

final readonly class DeleteResult
{
    public function __construct(public bool $deleted, public bool $purgePending) {}
}
