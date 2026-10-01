<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;

/**
 * Late or duplicate entity registration after the compiled projection froze.
 */
#[Api(since: '1.0')]
final class RegistryFrozen extends CodedException
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

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::RegistryFrozen, ErrorCategory::Internal, retryable: false);
    }
}
