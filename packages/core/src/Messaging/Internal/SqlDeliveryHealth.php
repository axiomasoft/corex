<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Messaging\Data\DeliveryHealth;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use Psr\Clock\ClockInterface;

final readonly class SqlDeliveryHealth
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private string $table = 'sys_outbox_messages',
    ) {}

    public function read(): DeliveryHealth
    {
        $table = $this->table;
        $now = $this->clock->now();
        $counts = [];

        $grouped = $this->connection->table($table)
            ->select('status')
            ->selectRaw('count(*) as total')
            ->groupBy('status')
            ->get();

        foreach ($grouped as $row) {
            $counts[(string) $row->status] = (int) $row->total;
        }

        $byContract = [];
        $oldest = null;

        $pending = $this->connection->table($table)
            ->select('name', 'contract_version')
            ->selectRaw('min(occurred_at) as oldest')
            ->where('status', 'pending')
            ->where('available_at', '<=', $now)
            ->groupBy('name', 'contract_version')
            ->get();

        foreach ($pending as $row) {
            $age = $now->getTimestamp() - $this->date($row->oldest)->getTimestamp();
            $byContract["{$row->name}.v{$row->contract_version}"] = $age;
            $oldest = $oldest === null ? $age : max($oldest, $age);
        }

        ksort($byContract);

        $attempts = $this->connection->table($table)
            ->whereIn('status', ['pending', 'publishing'])
            ->max('attempts');

        return new DeliveryHealth(
            oldestPendingSeconds: $oldest,
            oldestPendingSecondsByContract: $byContract,
            pendingCount: $counts['pending'] ?? 0,
            publishingCount: $counts['publishing'] ?? 0,
            deadCount: $counts['dead'] ?? 0,
            maxAttempts: (int) ($attempts ?? 0),
        );
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable((string) $value);
    }
}
