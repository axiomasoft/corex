<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use RuntimeException;

/**
 * Late or duplicate entity registration after the compiled projection froze.
 *
 * @internal
 */
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
