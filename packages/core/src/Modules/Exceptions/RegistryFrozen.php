<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

/**
 * Late or duplicate entity registration after the compiled projection froze.
 */
#[Api(since: '1.0')]
final class RegistryFrozen extends RuntimeException
{
    public static function late(string $point, string $id): self
    {
        return new self(sprintf(
            'Cannot register [%s:%s] after the entity collector is frozen.',
            $point,
            $id,
        ));
    }

    public static function duplicate(string $point, string $id): self
    {
        return new self(sprintf(
            'Duplicate [%s:%s] registration is rejected before mutation.',
            $point,
            $id,
        ));
    }
}
