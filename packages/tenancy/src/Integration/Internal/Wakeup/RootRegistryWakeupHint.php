<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Integration\Internal\Wakeup;

use CoreX\Context\Data\TenantContext;
use CoreX\Tenancy\Integration\Internal\DatabaseValue;
use CoreX\Wakeup\Contracts\WakeupScheduleHint;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * @internal Host-first wakeup adapter. Not package public API.
 *
 * Best-effort root routing hint. The tenant request remains authoritative;
 * losing this table merely makes the root process discover work later.
 */
final class RootRegistryWakeupHint implements WakeupScheduleHint
{
    public function __construct(
        private readonly ConnectionResolverInterface $connections,
        private readonly string $rootConnection,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {}

    public function scheduled(TenantContext $context, DateTimeImmutable $fireAt): void
    {
        try {
            $this->root()->transaction(function () use ($context, $fireAt): void {
                $hint = $this->root()->table('root_wakeup_hints')
                    ->where('account_id', $context->account->id)
                    ->lockForUpdate()
                    ->first();
                $now = $this->clock->now();

                if ($hint === null) {
                    $this->root()->table('root_wakeup_hints')->insert([
                        'account_id' => $context->account->id,
                        'next_fire_at' => $fireAt,
                        'hint_revision' => 1,
                        'leased_until' => null,
                        'claim_epoch' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    return;
                }

                $currentFireAt = new DateTimeImmutable(DatabaseValue::text($hint->next_fire_at));

                $this->root()->table('root_wakeup_hints')
                    ->where('account_id', $context->account->id)
                    ->where('hint_revision', $hint->hint_revision)
                    ->update([
                        'next_fire_at' => min($currentFireAt, $fireAt),
                        'hint_revision' => DatabaseValue::integer($hint->hint_revision) + 1,
                        'updated_at' => $now,
                    ]);
            });
        } catch (Throwable $exception) {
            // A hint only accelerates discovery. The tenant's committed request
            // remains the source of truth and reconciliation can recreate it.
            $this->logger->warning('Unable to publish CoreX root wakeup hint.', [
                'account_id' => $context->account->id,
                'exception' => $exception,
            ]);
        }
    }

    private function root(): ConnectionInterface
    {
        return $this->connections->connection($this->rootConnection);
    }
}
