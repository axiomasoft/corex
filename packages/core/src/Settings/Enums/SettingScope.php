<?php

declare(strict_types=1);

namespace CoreX\Settings\Enums;

use CoreX\Attributes\Api;

/**
 * Settings cascade level: user overrides workspace overrides
 * account overrides the manifest-declared default. `Department` is a
 * `FeatureFlags`-only override level — `sys_settings`'s CHECK constraint
 * still accepts only account/workspace/user; `DatabaseSettingsRepository`
 * throws `UnsupportedScopeException` if asked to read/write it.
 */
#[Api(since: '1.0')]
enum SettingScope: string
{
    case Account = 'account';
    case Workspace = 'workspace';
    case User = 'user';
    case Department = 'department';
}
