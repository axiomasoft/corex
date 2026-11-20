<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Operations\Idempotency\Contracts\IdempotencyStore;
use CoreX\Operations\Idempotency\Internal\SqlIdempotencyStore;
use CoreX\Operations\Laravel\Support\ApiOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Data\SecretRef;
use Psr\Clock\ClockInterface;

/** Builds the idempotency store; the key hash secret derives from `app.key`. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IdempotencyStoreFactory
{
    public function make(Application $app): IdempotencyStore
    {
        $secret = $app->make(Repository::class)->get('app.key');

        if (! is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('APP_KEY must be configured before resolving the idempotency store.');
        }

        return new SqlIdempotencyStore(
            connection: $app->make(Connection::class),
            current: $app->make(CurrentWorkScope::class),
            clock: $app->make(ClockInterface::class),
            keyHmacSecret: new SecretRef($secret),
            leaseSeconds: $app->make(ApiOptions::class)->idempotencyLeaseSeconds,
            retentionSeconds: $app->make(ApiOptions::class)->idempotencyRetentionSeconds,
        );
    }
}
