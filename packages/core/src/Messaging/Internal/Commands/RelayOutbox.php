<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal\Commands;

use CoreX\Config\Data\CoreOptions;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Contracts\OutboxRelay;
use CoreX\Messaging\Exceptions\OutboxLeaseLost;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Публикует ожидающие сообщения outbox текущего доверенного соединения.
 *
 * Работает на ОДНОМ соединении — том, которое дал контейнер. Планирование по многим
 * аккаунтам (round-robin, приоритет по возрасту, backoff по аккаунту) — вход P9
 * (research/P5-design.md §5.6).
 *
 * `--once` выполняет один проход и выходит: это режим планировщика. Без него команда
 * работает циклом и завершается по SIGTERM/SIGINT после текущего сообщения.
 *
 * @internal spec: P5.15 — console command is not a supported API
 */
final class RelayOutbox extends Command
{
    protected $signature = 'corex:messaging:relay
        {--batch= : Maximum messages per pass (default: corex.messaging.batch_size)}
        {--once : Run a single pass and exit}
        {--sleep=1 : Seconds to wait between empty passes}';

    protected $description = 'Publish pending CoreX outbox messages on the current trusted connection.';

    private bool $stopping = false;

    public function handle(OutboxRelay $relay, DurableMessagePublisher $publisher): int
    {
        /** @var string|null $batchOption */
        $batchOption = $this->option('batch');
        $batch = $batchOption === null ? app(CoreOptions::class)->messagingBatchSize : (int) $batchOption;

        if ($batch < 1 || $batch > 1_000) {
            $this->error('The --batch option must be between 1 and 1000.');

            return self::INVALID;
        }

        $this->listenForShutdown();
        $leaseOwner = (string) Str::uuid7();
        $published = $deferred = 0;

        do {
            $leased = $relay->lease(leaseOwner: $leaseOwner, batchSize: $batch);

            foreach ($leased as $message) {
                if ($this->stopping) {
                    break;
                }

                try {
                    $publisher->publish($message->envelope);
                    $relay->markPublished($message);
                    $published++;
                } catch (OutboxLeaseLost $lost) {
                    $this->warn($lost->getMessage());
                } catch (Throwable $error) {
                    $relay->releaseWithBackoff(message: $message, error: $error);
                    $deferred++;
                }
            }

            if (! $this->stopping && ! $this->option('once') && $leased === []) {
                sleep(max(1, (int) $this->option('sleep')));
            }
        } while (! $this->stopping && ! $this->option('once'));

        $this->info("Outbox relay: published {$published}; deferred {$deferred}.");

        return self::SUCCESS;
    }

    private function listenForShutdown(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGTERM, SIGINT] as $signal) {
            pcntl_signal($signal, function (): void {
                $this->stopping = true;
            });
        }
    }
}
