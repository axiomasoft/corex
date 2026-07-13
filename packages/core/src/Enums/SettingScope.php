<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Settings cascade level (B-10 §3.1): user overrides workspace overrides
 * account overrides the manifest-declared default.
 */
enum SettingScope: string
{
    case Account = 'account';
    case Workspace = 'workspace';
    case User = 'user';
}
