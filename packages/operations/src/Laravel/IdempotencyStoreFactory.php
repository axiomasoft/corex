<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\SecretRef;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Operations\Idempotency\Internal\SqlIdempotencyStore;
use CoreX\Operations\Laravel\Support\ApiOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

/** Builds the idempotency store; the key hash secret derives from `app.key`. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class IdempotencyStoreFactory
{
    public function make(Application $app): SqlIdempotencyStore
    {
        $secret = $app->make(Repository::class)->get('app.key');

        if (! is_string($secret) || $secret === '') {
            throw new InvalidArgumentException('APP_KEY must be configured before resolving the idempotency store.');
        }

        return new SqlIdempotencyStore(
            connection: $app->make(Connection::class),
            contexts: $app->make(TenantContextResolver::class),
            clock: $app->make(ClockInterface::class),
            keyHmacSecret: new SecretRef($secret),
            leaseSeconds: $app->make(ApiOptions::class)->idempotencyLeaseSeconds,
            retentionSeconds: $app->make(ApiOptions::class)->idempotencyRetentionSeconds,
        );
    }
}
