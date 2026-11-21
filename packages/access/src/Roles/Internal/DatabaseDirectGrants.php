<?php

declare(strict_types=1);

namespace CoreX\Access\Roles\Internal;

use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Roles\DirectGrants;
use CoreX\Context\Contracts\Principal;
use CoreX\Foundation\Laravel\ReferenceWrites;
use DateTimeImmutable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

/** Issues or revokes the tenant-wide direct permission of a single principal. */
#[Internal]
final readonly class DatabaseDirectGrants implements DirectGrants
{
    public function __construct(
        private ConnectionResolverInterface $connections,
        private AuthOptions $options,
        private ClockInterface $clock,
    ) {}

    public function grant(Principal $principal, string $entityKey, string $action, ?DateTimeImmutable $expiresAt = null): void
    {
        $this->write($principal, $entityKey, $action, $expiresAt, revoke: false);
    }

    public function revoke(Principal $principal, string $entityKey, string $action): void
    {
        $this->write($principal, $entityKey, $action, null, revoke: true);
    }

    private function write(Principal $principal, string $entityKey, string $action, ?DateTimeImmutable $expiresAt, bool $revoke): void
    {
        if ($entityKey === '' || preg_match('/\A[a-z][a-z0-9_-]*\z/', $action) !== 1) {
            throw new InvalidArgumentException('A direct grant requires an entity key and a single action name.');
        }

        $identity = [
            'principal_type' => $principal->principalType()->value,
            'principal_id' => $principal->principalId(),
            'permission_key' => $this->options->panel.'.'.$entityKey.'.'.$action,
        ];
        $connection = $this->connections->connection();
        $connection->transaction(function () use ($connection, $identity, $expiresAt, $revoke): void {
            ReferenceWrites::enlist($connection, ReferenceWrites::current(), 'axiomasoft/corex-access', 'grant', ['grant' => $identity]);
            // A first grant has no existing row to lock. Serialize grants and revocations for this principal and permission.
            $connection->selectOne(
                "SELECT pg_advisory_xact_lock(hashtextextended(jsonb_build_array('corex.access.direct', ?::text, ?::uuid, ?::text)::text, 0))",
                array_values($identity),
                false,
            );
            $active = $connection->table('aut_direct_grants')->where($identity)->whereNull('revoked_at');
            $now = $this->clock->now()->format('Y-m-d H:i:s.uP');

            if ($revoke) {
                $active->update(['revoked_at' => $now, 'updated_at' => $now]);

                return;
            }

            $expiry = $expiresAt?->format('Y-m-d H:i:s.uP');

            if ($active->update(['expires_at' => $expiry, 'updated_at' => $now]) === 0) {
                $connection->table('aut_direct_grants')->insert([
                    ...$identity,
                    'id' => (string) Str::uuid7(),
                    'expires_at' => $expiry,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
