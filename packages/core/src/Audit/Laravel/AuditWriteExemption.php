<?php

declare(strict_types=1);

namespace CoreX\Audit\Laravel;

use CoreX\Audit\Internal\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** @corex-model-extension Exact append-only AuditLog exemption for impersonation write guards; no arbitrary model opt-out. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AuditWriteExemption
{
    public static function permits(Model $model): bool
    {
        return $model::class === AuditLog::class;
    }
}
