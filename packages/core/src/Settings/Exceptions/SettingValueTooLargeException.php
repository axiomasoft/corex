<?php

declare(strict_types=1);

namespace CoreX\Settings\Exceptions;

use CoreX\Errors\Enums\CoreErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Exceptions\CodedException;

/**
 * Quota: `sys_settings.value` ≤ 64 KB encoded.
 */
#[Api(since: '1.0')]
final class SettingValueTooLargeException extends CodedException
{
    public function __construct(string $namespace, string $key, int $bytes, int $limit)
    {
        parent::__construct(sprintf(
            'Setting %s.%s value is %d bytes, exceeding the %d byte quota (B-10 §7.2).',
            $namespace,
            $key,
            $bytes,
            $limit,
        ));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(code: CoreErrorCode::SettingValueTooLargeException, category: ErrorCategory::Validation, retryable: false);
    }
}
