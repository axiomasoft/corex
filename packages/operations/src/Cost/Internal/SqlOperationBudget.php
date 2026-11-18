<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Internal;

use CoreX\Operations\Cost\Contracts\BudgetReservation;
use CoreX\Operations\Cost\Contracts\OperationBudget;
use CoreX\Operations\Cost\Data\ActualCost;
use CoreX\Operations\Cost\Data\BudgetScope;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Cost\Data\ThrottleStatus;
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
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/** @internal Shared SQL authority for atomic hierarchical operation budgets. */
#[Internal]
final readonly class SqlOperationBudget implements OperationBudget
{
    private const int MicroUnits = 1_000_000;

    private const string BucketTable = 'sys_api_budget_buckets';

    private const string ReservationTable = 'sys_api_budget_reservations';

    /** @var list<string> */
    private const array Levels = ['global', 'account', 'client', 'actor'];

    /** @var list<string> */
    private array $enforcedLevels;

    /** @param list<string> $enforcedLevels */
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private SecretRef $scopeHmacSecret,
        private int $capacity = 10_000,
        private int $restoreRate = 1_000,
        array $enforcedLevels = ['global'],
        private int $reservationTtlSeconds = 3_600,
    ) {
        if (! $scopeHmacSecret->isPresent()) {
            throw new InvalidArgumentException('Budget scope HMAC secret must be configured.');
        }

        $this->toMicroUnits(units: $capacity, label: 'capacity');

        if ($restoreRate < 1 || $restoreRate > intdiv(PHP_INT_MAX, self::MicroUnits)) {
            throw new InvalidArgumentException('Budget restore rate is outside its safe bounds.');
        }

        if ($reservationTtlSeconds < 1 || $reservationTtlSeconds > 3_600) {
            throw new InvalidArgumentException('Budget reservation TTL must be between 1 and 3600 seconds.');
        }

        $levels = array_values(array_unique($enforcedLevels));

        if (! in_array('global', $levels, true) || array_diff($levels, self::Levels) !== []) {
            throw new InvalidArgumentException('Budget enforcement must include global and only known hierarchy levels.');
        }

        $this->enforcedLevels = $levels;
    }

    #[Override]
    public function reserve(BudgetScope $scope, EstimatedCost $cost): BudgetReservation
    {
        $estimate = $this->toMicroUnits(units: $cost->units, label: 'estimated cost');
        $keysByLevel = $this->keys($scope);
        $attempts = $this->connection->getDriverName() === 'pgsql' ? 3 : 1;

        return $this->connection->transaction(function () use ($cost, $estimate, $keysByLevel): SqlBudgetReservation {
            $now = $this->clock->now();
            $capacity = $this->toMicroUnits(units: $this->capacity, label: 'capacity');
            $timestamp = $this->timestamp($now);

            foreach ($keysByLevel as $key) {
                $this->connection->table(self::BucketTable)->insertOrIgnore([
                    'key' => $key,
                    'capacity' => $capacity,
                    'available' => $capacity,
                    'refill_rate' => $this->restoreRate,
                    'last_refill_at' => $timestamp,
                    'last_used_at' => $timestamp,
                ]);
            }

            $keys = array_values($keysByLevel);
            sort($keys, SORT_STRING);
            $rows = $this->lockedBuckets($keys);
            $available = [];

            foreach ($rows as $key => $row) {
                $available[$key] = $this->refilledAvailable(row: $row, now: $now);
            }

            $retryAfter = 0;

            foreach ($this->enforcedLevels as $level) {
                $deficit = $this->deficit(required: $estimate, available: $available[$keysByLevel[$level]]);

                if ($deficit > 0) {
                    $restorePerSecond = $this->restoreRate * self::MicroUnits;
                    $retryAfter = max($retryAfter, intdiv($deficit - 1, $restorePerSecond) + 1);
                }
            }

            if ($retryAfter > 0) {
                throw new TooManyRequestsHttpException($retryAfter, 'Operation budget is exhausted.');
            }

            foreach ($keys as $key) {
                $this->connection->table(self::BucketTable)
                    ->where('key', $key)
                    ->update([
                        'available' => $this->subtract(left: $available[$key], right: $estimate),
                        'last_refill_at' => $this->timestamp($this->monotonicTime(row: $rows[$key], now: $now)),
                        'last_used_at' => $timestamp,
                    ]);
            }

            $id = (string) Str::uuid7();
            $this->connection->table(self::ReservationTable)->insert([
                'id' => $id,
                'keys' => JsonCanonical::encode($keys),
                'estimate' => $estimate,
                'charged' => $estimate,
                'recipe_hash' => $cost->recipeHash,
                'state' => 'held',
                'created_at' => $timestamp,
                'expires_at' => $this->timestamp($now->add(new DateInterval("PT{$this->reservationTtlSeconds}S"))),
            ]);

            $global = $available[$keysByLevel['global']] - $estimate;

            return new SqlBudgetReservation(
                budget: $this,
                id: $id,
                throttleStatus: new ThrottleStatus(
                    capacity: $this->capacity,
                    available: max(0, intdiv($global, self::MicroUnits)),
                    restoreRate: $this->restoreRate,
                    observedAt: $now,
                ),
            );
        }, $attempts);
    }

    public function settle(string $id, ActualCost $cost): void
    {
        $this->finish(id: $id, outcome: 'settled', charged: $this->toMicroUnits(units: $cost->units, label: 'actual cost'));
    }

    public function reject(string $id): void
    {
        $this->finish(id: $id, outcome: 'rejected', charged: 0);
    }

    private function finish(string $id, string $outcome, int $charged): void
    {
        $attempts = $this->connection->getDriverName() === 'pgsql' ? 3 : 1;

        $this->connection->transaction(function () use ($charged, $id, $outcome): void {
            $reservation = $this->connection->table(self::ReservationTable)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if ($reservation === null) {
                return;
            }

            $state = DatabaseValue::text(DatabaseValue::field(row: $reservation, name: 'state'));
            $storedCharge = DatabaseValue::integer(DatabaseValue::field(row: $reservation, name: 'charged'));

            if ($state === $outcome && $storedCharge === $charged) {
                return;
            }

            if ($state !== 'held') {
                throw new RuntimeException('Budget reservation already has a conflicting terminal outcome.');
            }

            $now = $this->clock->now();

            if ($this->date(DatabaseValue::field(row: $reservation, name: 'expires_at')) <= $now) {
                $this->connection->table(self::ReservationTable)
                    ->where('id', $id)
                    ->where('state', 'held')
                    ->update(['state' => 'abandoned']);

                return;
            }

            $keys = JsonCanonical::decode(DatabaseValue::text(DatabaseValue::field(row: $reservation, name: 'keys')));

            if (! is_array($keys) || ! array_is_list($keys) || count($keys) !== 4) {
                throw new RuntimeException('Budget reservation contains invalid bucket keys.');
            }

            $checkedKeys = [];
            foreach ($keys as $key) {
                if (! is_string($key) || preg_match('/\A[a-f0-9]{64}\z/D', $key) !== 1) {
                    throw new RuntimeException('Budget reservation contains invalid bucket keys.');
                }
                $checkedKeys[] = $key;
            }

            if (count(array_unique($checkedKeys)) !== 4) {
                throw new RuntimeException('Budget reservation contains invalid bucket keys.');
            }
            $keys = $checkedKeys;

            sort($keys, SORT_STRING);
            $rows = $this->lockedBuckets($keys);
            $estimate = DatabaseValue::integer(DatabaseValue::field(row: $reservation, name: 'estimate'));
            $adjustment = $estimate - $charged;

            foreach ($rows as $key => $row) {
                $available = $this->refilledAvailable(row: $row, now: $now);
                $capacity = DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'capacity'));
                $next = $adjustment >= 0
                    ? min($capacity, $this->add(left: $available, right: $adjustment))
                    : $this->subtract(left: $available, right: -$adjustment);

                $this->connection->table(self::BucketTable)
                    ->where('key', $key)
                    ->update([
                        'available' => $next,
                        'last_refill_at' => $this->timestamp($this->monotonicTime(row: $row, now: $now)),
                    ]);
            }

            $changed = $this->connection->table(self::ReservationTable)
                ->where('id', $id)
                ->where('state', 'held')
                ->update(['state' => $outcome, 'charged' => $charged]);

            if ($changed !== 1) {
                throw new RuntimeException('Budget reservation lost its terminal compare-and-swap fence.');
            }
        }, $attempts);
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, object>
     */
    private function lockedBuckets(array $keys): array
    {
        $rows = $this->connection->table(self::BucketTable)
            ->whereIn('key', $keys)
            ->orderBy('key')
            ->lockForUpdate()
            ->get();

        if ($rows->count() !== count($keys)) {
            throw new RuntimeException('Budget bucket set is incomplete.');
        }

        $indexed = [];

        foreach ($rows as $row) {
            $indexed[DatabaseValue::text(DatabaseValue::field($row, 'key'))] = $row;
        }

        return $indexed;
    }

    private function refilledAvailable(object $row, DateTimeImmutable $now): int
    {
        $available = DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'available'));
        $capacity = DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'capacity'));
        $elapsed = max(0, $this->epochMicroseconds($now) - $this->epochMicroseconds($this->date(DatabaseValue::field(row: $row, name: 'last_refill_at'))));

        if ($elapsed === 0 || $available >= $capacity) {
            return min($available, $capacity);
        }

        $rate = DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'refill_rate'));
        $refill = $elapsed > intdiv(PHP_INT_MAX, $rate) ? PHP_INT_MAX : $elapsed * $rate;

        return min($capacity, $this->add(left: $available, right: $refill));
    }

    private function monotonicTime(object $row, DateTimeImmutable $now): DateTimeImmutable
    {
        $previous = $this->date(DatabaseValue::field(row: $row, name: 'last_refill_at'));

        return $now >= $previous ? $now : $previous;
    }

    /** @return array{global: string, account: string, client: string, actor: string} */
    private function keys(BudgetScope $scope): array
    {
        $prefix = ['namespace' => 'corex.api.budget', 'version' => 1, 'installation' => $scope->installationId];
        $account = [...$prefix, 'account' => $scope->accountId];

        return [
            'global' => $this->key([...$prefix, 'level' => 'global']),
            'account' => $this->key([...$account, 'level' => 'account']),
            'client' => $this->key([...$account, 'level' => 'client', 'client' => $scope->clientId]),
            'actor' => $this->key([...$account, 'level' => 'actor', 'actor' => $scope->actorId]),
        ];
    }

    /** @param array<string, int|string> $tuple */
    private function key(array $tuple): string
    {
        return hash_hmac('sha256', JsonCanonical::encode($tuple), $this->scopeHmacSecret->reveal());
    }

    private function toMicroUnits(int $units, string $label): int
    {
        if ($units < 0 || $units > intdiv(PHP_INT_MAX, self::MicroUnits)) {
            throw new InvalidArgumentException("Budget {$label} is outside its safe bounds.");
        }

        return $units * self::MicroUnits;
    }

    private function add(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            return PHP_INT_MAX;
        }

        return $left + $right;
    }

    private function subtract(int $left, int $right): int
    {
        if ($right > 0 && $left < PHP_INT_MIN + $right) {
            throw new RuntimeException('Budget arithmetic underflow.');
        }

        return $left - $right;
    }

    private function deficit(int $required, int $available): int
    {
        if ($available >= $required) {
            return 0;
        }

        if ($available < 0 && $required > PHP_INT_MAX + $available) {
            return PHP_INT_MAX;
        }

        return $required - $available;
    }

    private function epochMicroseconds(DateTimeImmutable $time): int
    {
        return ((int) $time->format('U') * self::MicroUnits) + (int) $time->format('u');
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->format('Y-m-d H:i:s.uP');
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable(DatabaseValue::text($value));
    }
}
