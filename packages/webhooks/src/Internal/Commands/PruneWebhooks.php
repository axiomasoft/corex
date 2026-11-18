<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal\Commands;

use CoreX\Data\Data\DataScope;
use CoreX\Webhooks\Internal\WebhookRetention;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

/** @internal Bounded retention for an already restored trusted tenant scope. */
#[Internal]
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

        $result = $webhooks->prune(scope: $this->laravel->make(DataScope::class), limit: $max);
        $this->components->info(sprintf('Webhook retention: bodies %d attempts %d inbox %d.', $result['bodies'], $result['attempts'], $result['inbox']));

        return self::SUCCESS;
    }
}
