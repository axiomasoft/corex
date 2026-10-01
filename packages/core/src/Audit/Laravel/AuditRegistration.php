<?php

declare(strict_types=1);

namespace CoreX\Audit\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Internal\AuditObserver;
use Illuminate\Database\Eloquent\Model;

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
