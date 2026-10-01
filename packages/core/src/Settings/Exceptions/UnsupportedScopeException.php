<?php

declare(strict_types=1);

namespace CoreX\Settings\Exceptions;

use CoreX\Attributes\Api;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\CodedException;
use CoreX\Settings\Enums\SettingScope;

/**
 * `sys_settings`'s CHECK constraint accepts only account/workspace/user —
 * `SettingsScope::department()` is a `FeatureFlags`-only override level
 * (`sys_feature_overrides` CHECK already allows it). A caller handing
 * a department scope to `SettingsRepository` gets this instead of a silent
 * account-level write.
 */
#[Api(since: '1.0')]
final class UnsupportedScopeException extends CodedException
{
    public function __construct(SettingScope $scope)
    {
        parent::__construct(sprintf(
            'SettingsRepository does not support scope "%s" — sys_settings only accepts account/workspace/user (D38).',
            $scope->value,
        ));
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor(CoreErrorCode::UnsupportedScopeException, ErrorCategory::Unsupported, retryable: false);
    }
}
