<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Internal;

use CoreX\Operations\Validation\Laravel\JsonCanonical;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

/**
 * Shared-budget retention. Expiry abandons a held reservation without
 * refunding units. Held reservations keep their buckets. A missing
 * reservation is not a refund.
 *
 * @internal
 */
final readonly class BudgetRetention
{
    public const int BatchLimit = 100;

    public const int DefaultTerminalSeconds = 2_592_000;

    private const string Buckets = 'sys_api_budget_buckets';

    private const string Reservations = 'sys_api_budget_reservations';

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private int $terminalRetentionSeconds = self::DefaultTerminalSeconds,
    ) {
        if ($this->terminalRetentionSeconds < 1) {
            throw new InvalidArgumentException('Budget terminal retention must be positive.');
        }
    }

    public function abandonExpired(int $limit = self::BatchLimit): int
    {
        $this->assertLimit($limit);
        $now = $this->clock->now();
        $ids = $this->connection->table(self::Reservations)
            ->where('state', 'held')
            ->where('expires_at', '<=', $now)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        return $this->connection->transaction(fn (): int => $this->connection->table(self::Reservations)
            ->whereIn('id', $ids)
            ->where('state', 'held')
            ->update(['state' => 'abandoned']), $this->attempts());
    }

    public function release(string $id): bool
    {
        return $this->connection->transaction(function () use ($id): bool {
            $row = $this->connection->table(self::Reservations)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if ($row === null || DatabaseValue::text(DatabaseValue::field(row: $row, name: 'state')) !== 'held') {
                return false;
            }

            if ($this->instant(DatabaseValue::field(row: $row, name: 'expires_at')) > $this->clock->now()) {
                return false;
            }

            $changed = $this->connection->table(self::Reservations)
                ->where('id', $id)
                ->where('state', 'held')
                ->update(['state' => 'abandoned']);

            return $changed === 1;
        }, $this->attempts());
    }

    public function pruneTerminal(int $limit = self::BatchLimit): int
    {
        $this->assertLimit($limit);
        $cutoff = $this->clock->now()->sub(new DateInterval('PT'.$this->terminalRetentionSeconds.'S'));
        $ids = $this->connection->table(self::Reservations)
            ->whereIn('state', ['settled', 'rejected', 'abandoned'])
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        return $this->connection->table(self::Reservations)->whereIn('id', $ids)->delete();
    }

    public function forgetIdleBuckets(int $limit = self::BatchLimit): int
    {
        $this->assertLimit($limit);
        $protected = $this->protectedKeys();

        if ($protected === null) {
            return 0;
        }

        $cutoff = $this->clock->now()->sub(new DateInterval('PT'.$this->terminalRetentionSeconds.'S'));
        $keys = $this->connection->table(self::Buckets)
            ->where('last_used_at', '<=', $cutoff)
            ->orderBy('key')
            ->limit($limit)
            ->pluck('key')
            ->all();
        $deletable = [];

        foreach ($keys as $key) {
            $key = DatabaseValue::text($key);

            if (! isset($protected[$key])) {
                $deletable[] = $key;
            }
        }

        if ($deletable === []) {
            return 0;
        }

        return $this->connection->table(self::Buckets)->whereIn('key', $deletable)->delete();
    }

    private function assertLimit(int $limit): void
    {
        if ($limit < 1 || $limit > self::BatchLimit) {
            throw new InvalidArgumentException('Budget retention batch must be between 1 and 100.');
        }
    }

    /**
     * @return array<string, true>|null
     */
    private function protectedKeys(): ?array
    {
        $protected = [];

        foreach ($this->connection->table(self::Reservations)->where('state', 'held')->get(['keys']) as $row) {
            $keys = JsonCanonical::decode(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'keys')));

            if (! is_array($keys) || ! array_is_list($keys)) {
                return null;
            }

            foreach ($keys as $key) {
                if (! is_string($key) || preg_match('/\A[a-f0-9]{64}\z/D', $key) !== 1) {
                    return null;
                }

                $protected[$key] = true;
            }
        }

        return $protected;
    }

    /** @return int<1, 3> */
    private function attempts(): int
    {
        return $this->connection->getDriverName() === 'pgsql' ? 3 : 1;
    }

    private function instant(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable(DatabaseValue::text($value));
    }
}
