<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use DateTimeImmutable;
use DateTimeZone;
use Packman\Attributes\Internal;

#[Internal]
final class DigestRunIdentity
{
    public const string Owner = 'notifications.digest';

    public static function reference(string $runId, DateTimeImmutable $start, DateTimeImmutable $end, string $period): string
    {
        return $runId.':'.hash('sha256', json_encode([
            self::timestamp($start),
            self::timestamp($end),
            $period,
        ], JSON_THROW_ON_ERROR));
    }

    public static function timestamp(DateTimeImmutable $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.uP');
    }
}
