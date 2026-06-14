<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface AuditLogger
{
    /**
     * Record an append-only audit entry for a model change.
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function log(Auditable $model, string $event, array $changes = [], ?Authenticatable $actor = null): void;
}
