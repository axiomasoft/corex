<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal\Commands;

use CoreX\Messaging\Internal\SqlDeliveryHealth;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

/**
 * Reports the four outbox health measures and the age of pending messages by contract.
 *
 * @internal Console commands are outside the supported PHP API.
 */
#[Internal]
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
        $this->line('Quarantined: '.$snapshot->quarantinedCount);
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
