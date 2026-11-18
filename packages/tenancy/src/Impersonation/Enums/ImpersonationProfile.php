<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Enums;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
enum ImpersonationProfile: string
{
    case Dev = 'dev';
    case Standard = 'standard';
    case Regulated = 'regulated';

    public function maxGrantMinutes(): int
    {
        return match ($this) {
            self::Dev => 480,
            self::Standard => 60,
            self::Regulated => 30,
        };
    }

    public function requiresIndependentApproval(): bool
    {
        return $this === self::Regulated;
    }

    public function mirrorsToTenant(): bool
    {
        return $this !== self::Dev;
    }
}
