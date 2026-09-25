<?php

declare(strict_types=1);

namespace CoreX\Transfer\Internal\Transfer;

use Closure;
use CoreX\Transfer\Data\SourceCursor;
use CoreX\Transfer\Data\TransferLimits;

final class ExecutionGuard
{
    /**
     * @param  Closure(): int  $monotonicNanoseconds
     * @param  Closure(): int  $memoryBytes
     */
    public function __construct(
        private readonly TransferLimits $limits,
        private readonly Closure $monotonicNanoseconds,
        private readonly Closure $memoryBytes,
        private readonly int $startedAt,
    ) {}

    public static function production(TransferLimits $limits): self
    {
        $monotonic = static fn (): int => hrtime(true);

        return new self(
            limits: $limits,
            monotonicNanoseconds: $monotonic,
            memoryBytes: static fn (): int => memory_get_usage(true),
            startedAt: $monotonic(),
        );
    }

    /**
     * @param  Closure(): int  $monotonicNanoseconds
     * @param  Closure(): int  $memoryBytes
     */
    public static function injected(TransferLimits $limits, Closure $monotonicNanoseconds, Closure $memoryBytes): self
    {
        return new self(
            limits: $limits,
            monotonicNanoseconds: $monotonicNanoseconds,
            memoryBytes: $memoryBytes,
            startedAt: $monotonicNanoseconds(),
        );
    }

    /**
     * @param  array<string, int>  $stats
     */
    public function assertWithin(SourceCursor $cursor, string $checkpointSequence, array $stats): void
    {
        $elapsed = ($this->monotonicNanoseconds)() - $this->startedAt;

        if ($elapsed > $this->limits->maxRuntimeSeconds * 1_000_000_000) {
            throw new TransferLimitReached(
                errorCode: 'runtimeLimitReached',
                cursor: $cursor,
                checkpointSequence: $checkpointSequence,
                stats: $stats,
            );
        }

        if (($this->memoryBytes)() > $this->limits->maxMemoryMiB * 1_048_576) {
            throw new TransferLimitReached(
                errorCode: 'memoryLimitReached',
                cursor: $cursor,
                checkpointSequence: $checkpointSequence,
                stats: $stats,
            );
        }
    }
}
