<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Integration\Internal\Wakeup;

use Illuminate\Console\Command;
use Packman\Attributes\Internal;

/** @internal Host-first wakeup command. Not package public API. */
#[Internal]
final class SweepRootWakeups extends Command
{
    protected $signature = 'corex:tenancy:wakeup-root-sweep {--limit=100 : Maximum due account hints} {--reconcile : Rebuild one bounded page of advisory hints} {--cursor= : Resume after this account id}';

    protected $description = 'Run due CoreX wakeup hints through trusted tenant context switching.';

    public function handle(RootWakeupReconciler $reconciler): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < 1 || $limit > 1_000) {
            $this->error('The --limit option must be between 1 and 1000.');

            return self::INVALID;
        }

        if ((bool) $this->option('reconcile')) {
            $cursor = $this->option('cursor');

            if ($cursor !== null && ! is_string($cursor)) {
                $this->error('The --cursor option must be an account id.');

                return self::INVALID;
            }
            $result = $reconciler->reconcile(limit: $limit, afterAccountId: $cursor);
            $this->info("Reconciled {$result['processed']} account wakeup hints.");

            if ($result['next_cursor'] !== null) {
                $this->line("Resume with --cursor={$result['next_cursor']}.");
            }

            return self::SUCCESS;
        }

        $this->info("Swept {$reconciler->sweep(limit: $limit)} account hints.");

        return self::SUCCESS;
    }
}
