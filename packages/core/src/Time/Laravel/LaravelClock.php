<?php

declare(strict_types=1);

namespace CoreX\Time\Laravel;

use DateTimeImmutable;
use Illuminate\Support\DateFactory;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Psr\Clock\ClockInterface;

/**
 * Часы приложения Laravel: видят Carbon::setTestNow() и хелперы travelTo()/freezeTime(),
 * поэтому существующие тесты, управляющие временем через фреймворк, продолжают работать
 * после замены now() на инъектированные часы.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class LaravelClock implements ClockInterface
{
    public function __construct(private DateFactory $dates) {}

    #[Override]
    public function now(): DateTimeImmutable
    {
        return $this->dates->now()->toDateTimeImmutable();
    }
}
