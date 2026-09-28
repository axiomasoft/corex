<?php

declare(strict_types=1);

namespace CoreX\Api\Commands;

use CoreX\Api\Internal\Webhook\WebhookRetention;
use CoreX\Support\Data\DataScope;
use Illuminate\Console\Command;

/** @internal Bounded retention for an already restored trusted tenant scope. */
final class PruneWebhooks extends Command
{
    protected $signature = 'corex:webhooks:prune {--max=100 : Maximum rows in this pass}';

    protected $description = 'Prune expired CoreX webhook bodies, attempts and inbox history.';

    public function handle(WebhookRetention $webhooks): int
    {
        $max = (int) $this->option('max');

        if ($max < 1 || $max > 100) {
            $this->components->error('The --max option must be between 1 and 100.');

            return self::INVALID;
        }

        if (! $this->laravel->bound(DataScope::class)) {
            $this->components->warn('No trusted tenant scope is active.');

            return self::SUCCESS;
        }

        $result = $webhooks->prune($this->laravel->make(DataScope::class), $max);
        $this->components->info(sprintf('Webhook retention: bodies %d attempts %d inbox %d.', $result['bodies'], $result['attempts'], $result['inbox']));

        return self::SUCCESS;
    }
}
