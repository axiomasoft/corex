<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Operations\Idempotency\Contracts\IdempotencyStore;
use CoreX\Operations\Idempotency\Data\IdempotencyAttempt;
use CoreX\Operations\Idempotency\Data\IdempotencyScope;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Override;
use Packman\Attributes\Internal;
use Packman\Config\Data\SecretRef;
use Psr\Clock\ClockInterface;
use RuntimeException;

/** @internal */
#[Internal]
final readonly class SqlIdempotencyStore implements IdempotencyStore
{
    private const string Table = 'sys_api_idempotency';

    public function __construct(
        private Connection $connection,
        private TenantContextResolver $contexts,
        private ClockInterface $clock,
        private SecretRef $keyHmacSecret,
        private int $leaseSeconds,
        private int $retentionSeconds,
    ) {
        if (! $keyHmacSecret->isPresent()) {
            throw new InvalidArgumentException('Idempotency key HMAC secret must be configured.');
        }

        if ($leaseSeconds < 1 || $retentionSeconds < 1) {
            throw new InvalidArgumentException('Idempotency lease and retention must be positive.');
        }
    }

    #[Override]
    public function begin(
        IdempotencyScope $scope,
        string $key,
        string $requestFingerprint,
        string $contractHash,
    ): IdempotencyAttempt {
        $this->assertScope($scope);
        $this->assertTransaction();
        $this->assertKey($key);
        $this->assertDigest(value: $requestFingerprint, label: 'request fingerprint');
        $this->assertDigest(value: $contractHash, label: 'contract hash');

        $now = $this->clock->now();
        $scopeHash = hash('sha256', JsonCanonical::encode($scope->toArray()));
        $keyHash = hash_hmac('sha256', $key, $this->keyHmacSecret->reveal());
        $leaseToken = (string) Str::uuid7();
        $leaseUntil = $now->add(new DateInterval("PT{$this->leaseSeconds}S"));

        $inserted = $this->connection->table(self::Table)->insertOrIgnore([
            'scope_hash' => $scopeHash,
            'key_hash' => $keyHash,
            'request_fingerprint' => $requestFingerprint,
            'state' => 'started',
            'attempt' => 1,
            'lease_token' => $leaseToken,
            'lease_until' => $leaseUntil,
            'outcome_json' => null,
            'contract_hash' => $contractHash,
            'expires_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 1) {
            return new IdempotencyAttempt(
                disposition: IdempotencyAttempt::Owner,
                scope: $scope,
                scopeHash: $scopeHash,
                keyHash: $keyHash,
                requestFingerprint: $requestFingerprint,
                contractHash: $contractHash,
                state: 'started',
                attempt: 1,
                leaseToken: $leaseToken,
                leaseUntil: $leaseUntil,
                outcomeJson: null,
            );
        }

        $row = $this->connection->table(self::Table)
            ->where('scope_hash', $scopeHash)
            ->where('key_hash', $keyHash)
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw new RuntimeException('Idempotency row disappeared during admission.');
        }

        return $this->decision(scope: $scope, row: $row, requestFingerprint: $requestFingerprint, contractHash: $contractHash);
    }

    #[Override]
    public function takeover(IdempotencyAttempt $attempt): IdempotencyAttempt
    {
        $this->assertScope($attempt->scope);
        $this->assertTransaction();

        if ($attempt->disposition !== IdempotencyAttempt::InProgress || $attempt->state !== 'started' || $attempt->leaseToken === null || $attempt->leaseUntil === null) {
            throw new InvalidArgumentException('Only an observed started attempt can be taken over.');
        }

        $now = $this->clock->now();

        if ($attempt->leaseUntil > $now) {
            throw new RuntimeException('Idempotency lease is still active.');
        }

        $leaseToken = (string) Str::uuid7();
        $leaseUntil = $now->add(new DateInterval("PT{$this->leaseSeconds}S"));
        $nextAttempt = $attempt->attempt + 1;
        $changed = $this->connection->table(self::Table)
            ->where('scope_hash', $attempt->scopeHash)
            ->where('key_hash', $attempt->keyHash)
            ->where('request_fingerprint', $attempt->requestFingerprint)
            ->where('contract_hash', $attempt->contractHash)
            ->where('state', 'started')
            ->where('attempt', $attempt->attempt)
            ->where('lease_token', $attempt->leaseToken)
            ->where('lease_until', '<=', $now)
            ->update([
                'attempt' => $nextAttempt,
                'lease_token' => $leaseToken,
                'lease_until' => $leaseUntil,
                'updated_at' => $now,
            ]);

        if ($changed !== 1) {
            throw new RuntimeException('Idempotency takeover lost its compare-and-swap fence.');
        }

        return new IdempotencyAttempt(
            disposition: IdempotencyAttempt::Owner,
            scope: $attempt->scope,
            scopeHash: $attempt->scopeHash,
            keyHash: $attempt->keyHash,
            requestFingerprint: $attempt->requestFingerprint,
            contractHash: $attempt->contractHash,
            state: 'started',
            attempt: $nextAttempt,
            leaseToken: $leaseToken,
            leaseUntil: $leaseUntil,
            outcomeJson: null,
        );
    }

    #[Override]
    public function complete(IdempotencyAttempt $attempt, string $outcomeJson): void
    {
        $this->terminal(attempt: $attempt, state: 'completed', outcomeJson: $outcomeJson);
    }

    #[Override]
    public function fail(IdempotencyAttempt $attempt, string $outcomeJson): void
    {
        $this->terminal(attempt: $attempt, state: 'failed', outcomeJson: $outcomeJson);
    }

    #[Override]
    public function prune(): int
    {
        return $this->connection->table(self::Table)
            ->whereIn('state', ['completed', 'failed'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $this->clock->now())
            ->delete();
    }

    private function terminal(IdempotencyAttempt $attempt, string $state, string $outcomeJson): void
    {
        $this->assertScope($attempt->scope);
        $this->assertTransaction();

        if (! $attempt->ownsLease() || $attempt->leaseToken === null) {
            throw new InvalidArgumentException('Only the current lease owner can persist an outcome.');
        }

        $decoded = JsonCanonical::decodeObject($outcomeJson);

        if ($decoded === null || JsonCanonical::encode($decoded) !== $outcomeJson) {
            throw new InvalidArgumentException('Idempotency outcome must be a canonical JSON object.');
        }

        $now = $this->clock->now();
        $changed = $this->connection->table(self::Table)
            ->where('scope_hash', $attempt->scopeHash)
            ->where('key_hash', $attempt->keyHash)
            ->where('request_fingerprint', $attempt->requestFingerprint)
            ->where('contract_hash', $attempt->contractHash)
            ->where('state', 'started')
            ->where('attempt', $attempt->attempt)
            ->where('lease_token', $attempt->leaseToken)
            ->update([
                'state' => $state,
                'outcome_json' => $outcomeJson,
                'lease_token' => null,
                'lease_until' => null,
                'expires_at' => $now->add(new DateInterval("PT{$this->retentionSeconds}S")),
                'updated_at' => $now,
            ]);

        if ($changed !== 1) {
            throw new RuntimeException('Idempotency lease was superseded before terminal persistence.');
        }
    }

    private function decision(
        IdempotencyScope $scope,
        object $row,
        string $requestFingerprint,
        string $contractHash,
    ): IdempotencyAttempt {
        $state = DatabaseValue::text(DatabaseValue::field(row: $row, name: 'state'));
        $disposition = match (true) {
            ! hash_equals(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'request_fingerprint')), $requestFingerprint) => IdempotencyAttempt::Conflict,
            ! hash_equals(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'contract_hash')), $contractHash) => IdempotencyAttempt::ContractMismatch,
            in_array($state, ['completed', 'failed'], true) => IdempotencyAttempt::Replay,
            $state === 'started' => IdempotencyAttempt::InProgress,
            default => throw new RuntimeException('Idempotency row has an unknown state.'),
        };

        return new IdempotencyAttempt(
            disposition: $disposition,
            scope: $scope,
            scopeHash: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'scope_hash')),
            keyHash: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'key_hash')),
            requestFingerprint: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'request_fingerprint')),
            contractHash: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'contract_hash')),
            state: $state,
            attempt: DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'attempt')),
            leaseToken: DatabaseValue::field(row: $row, name: 'lease_token') === null ? null : DatabaseValue::text(DatabaseValue::field(row: $row, name: 'lease_token')),
            leaseUntil: $this->date(DatabaseValue::field(row: $row, name: 'lease_until')),
            outcomeJson: DatabaseValue::field(row: $row, name: 'outcome_json') === null ? null : DatabaseValue::text(DatabaseValue::field(row: $row, name: 'outcome_json')),
        );
    }

    private function assertScope(IdempotencyScope $scope): void
    {
        $current = $this->contexts->current();

        if ($current->account->id !== $scope->accountId
            || $current->workspace?->id !== $scope->workspaceId
            || $this->connection->getName() !== $scope->connectionName
            || $this->connection->getDatabaseName() !== $scope->databaseName) {
            throw new RuntimeException('Idempotency scope does not match the active tenant connection.');
        }
    }

    private function assertTransaction(): void
    {
        if ($this->connection->transactionLevel() < 1) {
            throw new RuntimeException('Idempotency mutation requires an active tenant transaction.');
        }
    }

    private function assertKey(string $key): void
    {
        $length = strlen($key);

        if ($length < 1 || $length > 255) {
            throw new InvalidArgumentException('Idempotency key must contain 1-255 bytes.');
        }
    }

    private function assertDigest(string $value, string $label): void
    {
        if (preg_match('/\A[a-f0-9]{64}\z/D', $value) !== 1) {
            throw new InvalidArgumentException("Idempotency {$label} must be a lowercase SHA-256 digest.");
        }
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable(DatabaseValue::text($value));
    }
}
