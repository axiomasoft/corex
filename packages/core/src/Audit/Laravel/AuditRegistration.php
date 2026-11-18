<?php

declare(strict_types=1);

namespace CoreX\Audit\Laravel;

use CoreX\Audit\Internal\AuditObserver;
use Illuminate\Database\Eloquent\Model;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** @corex-model-extension Registers created/updated/deleted/restored/forceDeleted audit hooks; no extra columns. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AuditRegistration
{
    /** @param class-string<Model> $model */
    public static function observe(string $model): void
    {
        $model::observe(AuditObserver::class);
    }
}
