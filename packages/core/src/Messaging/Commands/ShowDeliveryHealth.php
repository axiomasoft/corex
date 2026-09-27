<?php

declare(strict_types=1);

namespace CoreX\Messaging\Commands;

use CoreX\Messaging\Internal\SqlDeliveryHealth;
use Illuminate\Console\Command;

/**
 * Печатает четыре агрегата здоровья outbox и разрез возраста по контракту.
 *
 * Экспорт в OpenTelemetry, имена спанов и метрики messaging.* — обязательство P12, не P5
 * (research/P5-design.md §9.4).
 *
 * @internal spec: P5.15 — console command is not a supported API
 */
final class ShowDeliveryHealth extends Command
{
    protected $signature = 'corex:messaging:health
        {--max-age= : Fail when oldest pending age exceeds this many seconds (probe mode)}';

    protected $description = 'Report CoreX outbox delivery health on the current trusted connection.';

    public function handle(SqlDeliveryHealth $health): int
    {
        /** @var string|null $maxAgeOption */
        $maxAgeOption = $this->option('max-age');

        if ($maxAgeOption !== null && ! ctype_digit($maxAgeOption)) {
            $this->error('The --max-age option must be a non-negative integer number of seconds.');

            return self::INVALID;
        }

        $snapshot = $health->read();

        $oldest = $snapshot->oldestPendingSeconds;
        $this->line('Oldest pending age (seconds): '.($oldest === null ? 'none' : (string) $oldest));

        $this->line('Oldest pending by contract:');

        if ($snapshot->oldestPendingSecondsByContract === []) {
            $this->line('  (none)');
        } else {
            foreach ($snapshot->oldestPendingSecondsByContract as $contract => $seconds) {
                $this->line("  {$contract}: {$seconds}");
            }
        }

        $this->line('Pending: '.$snapshot->pendingCount);
        $this->line('Publishing: '.$snapshot->publishingCount);
        $this->line('Dead: '.$snapshot->deadCount);
        $this->line('Max attempts (pending/publishing): '.$snapshot->maxAttempts);

        if ($maxAgeOption !== null) {
            $maxAge = (int) $maxAgeOption;

            if ($oldest !== null && $oldest > $maxAge) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
