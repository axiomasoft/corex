<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use CoreX\Context\Contracts\Principal;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/** Finds an active direct grant for the exact principal type, ID and permission in the tenant connection. */
#[Internal]
final readonly class DirectGrantLookup
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private ClockInterface $clock,
    ) {}

    public function hasActive(Principal $p, string $key, ?DateTimeImmutable $at = null, ?ConnectionInterface $connection = null): bool
    {
        $now = ($at ?? $this->clock->now())->format('Y-m-d H:i:s.uP');

        return ($connection ?? $this->connections->connection())->table('aut_direct_grants')
            ->where('principal_type', $p->principalType()->value)
            ->where('principal_id', $p->principalId())
            ->where('permission_key', $key)
            ->whereNull('revoked_at')
            ->where(static function (Builder $q) use ($now): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->exists();
    }
}
