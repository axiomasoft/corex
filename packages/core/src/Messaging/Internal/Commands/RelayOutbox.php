<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal\Commands;

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Messaging\Contracts\DurableMessagePublisher;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Contracts\OutboxRelay;
use CoreX\Messaging\Exceptions\InvalidMessage;
use CoreX\Messaging\Exceptions\OutboxLeaseLost;
use CoreX\Messaging\Exceptions\UnknownMessageContract;
use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Throwable;

/**
 * Publishes pending messages for the trusted connection supplied by the container.
 * The host schedules work across accounts and decides their priority and backoff.
 *
 * `--once` runs a single scheduler pass. In continuous mode, the relay finishes
 * the current message before exiting on SIGTERM or SIGINT.
 *
 * @internal Console commands are outside the supported PHP API.
 */
#[Internal]
final class RelayOutbox extends Command
{
    protected $signature = 'corex:messaging:relay
        {--batch= : Maximum messages per pass (default: corex.messaging.batch_size)}
        {--once : Run a single pass and exit}
        {--sleep=1 : Seconds to wait between empty passes}';

    protected $description = 'Publish pending CoreX outbox messages on the current trusted connection.';

    private bool $stopping = false;

    public function handle(OutboxRelay $relay, StoredWorkRunner $runner, CoreOptions $options): int
    {
        /** @var string|null $batchOption */
        $batchOption = $this->option('batch');
        $batch = $batchOption === null ? $options->messagingBatchSize : (int) $batchOption;

        if ($batch < 1 || $batch > 1_000) {
            $this->error('The --batch option must be between 1 and 1000.');

            return self::INVALID;
        }

        if (! $this->laravel->bound(DurableMessagePublisher::class)) {
            throw new BindingResolutionException('A durable broker publisher is required.');
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
                    try {
                        $envelope = $message->envelope;
                        $failure = $runner->runStored(scope: $envelope->scope, proof: $envelope->workProof(), work: function () use ($envelope, $relay, $message): ?Throwable {
                            $contract = $this->laravel->make(MessageContractRegistry::class)->get($envelope->name, $envelope->contractVersion);
                            $contract->validateFact($envelope);
                            $relay->assertClaim($message);

                            try {
                                $this->laravel->make(DurableMessagePublisher::class)->publish($envelope);
                            } catch (Throwable $failure) {
                                return $failure;
                            }
                            $relay->markPublished($message);

                            return null;
                        });

                        if ($failure instanceof Throwable) {
                            throw $failure;
                        }
                        $published++;
                    } catch (OutboxLeaseLost $lost) {
                        throw $lost;
                    } catch (InvalidMessage|UnknownMessageContract|ScopeContextMismatch|ScopeStale $error) {
                        $relay->quarantine(message: $message, error: $error);
                    } catch (Throwable $error) {
                        $relay->releaseWithBackoff(message: $message, error: $error);
                        $deferred++;
                    }
                } catch (OutboxLeaseLost $lost) {
                    $this->warn($lost->getMessage());
                }
            }

            if (! $this->stopping && ! $this->option('once') && $leased === []) {
                Sleep::sleep(max(1, (int) $this->option('sleep')));
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
