<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal\Commands;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Webhooks\Internal\WebhookRetention;
use Illuminate\Console\Command;
use Packman\Attributes\Internal;

/** @internal Bounded retention for an already restored trusted tenant scope. */
#[Internal]
final class PruneWebhooks extends Command
{
    protected $signature = 'corex:webhooks:prune {--max=100 : Maximum rows in this pass}';

    protected $description = 'Prune expired CoreX webhook bodies and attempt history.';

    public function handle(): int
    {
        $max = (int) $this->option('max');

        if ($max < 1 || $max > 100) {
            $this->components->error('The --max option must be between 1 and 100.');

            return self::INVALID;
        }

        try {
            $scope = $this->laravel->make(DataScopeFactory::class)->forCurrentPrincipal($this->laravel->make(TenantContextResolver::class)->current());
        } catch (CentralContextException|ScopeContextMismatch) {
            $this->components->warn('No trusted tenant scope is active.');

            return self::SUCCESS;
        }

        $result = $this->laravel->make(WorkScopeRunner::class)->run(
            $scope,
            fn (): array => $this->laravel->make(WebhookRetention::class)->prune(scope: $scope, limit: $max),
        );
        $this->components->info(sprintf('Webhook retention: bodies %d attempts %d inbox %d.', $result['bodies'], $result['attempts'], $result['inbox']));

        return self::SUCCESS;
    }
}
