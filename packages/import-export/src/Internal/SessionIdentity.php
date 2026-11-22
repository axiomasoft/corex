<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use Packman\Attributes\Internal;

/** Durable session metadata lives inside the package-owned source manifest. */
#[Internal]
final class SessionIdentity
{
    public static function scopeHash(DataScope $scope): string
    {
        return hash('sha256', json_encode([DataScope::VERSION, $scope], JSON_THROW_ON_ERROR));
    }

    public static function assertScope(object $job, DataScope $scope): void
    {
        if (! hash_equals(Value::text(Value::field(row: $job, name: 'restore_epoch')), $scope->restoreEpoch)) {
            throw new SessionUnavailable('The transfer belongs to another restore epoch.');
        }

        $manifest = json_decode(Value::text(Value::field(row: $job, name: 'source_manifest')), true, 32, JSON_THROW_ON_ERROR);

        if (! is_array($manifest) || ! is_string($manifest['_scope_hash'] ?? null) || ! hash_equals($manifest['_scope_hash'], self::scopeHash($scope))) {
            throw new SessionUnavailable('The transfer belongs to another data scope.');
        }
    }
}
