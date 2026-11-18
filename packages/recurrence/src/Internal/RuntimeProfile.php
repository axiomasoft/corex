<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Internal;

use Composer\InstalledVersions;
use Packman\Attributes\Internal;

#[Internal]
final class RuntimeProfile
{
    public static function tzdbVersion(): string
    {
        $version = timezone_version_get();

        return $version !== '' ? $version : 'unknown';
    }

    public static function libraryVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('rlanvin/php-rrule')) {
            return InstalledVersions::getVersion('rlanvin/php-rrule') ?? 'unknown';
        }

        return 'unknown';
    }
}
