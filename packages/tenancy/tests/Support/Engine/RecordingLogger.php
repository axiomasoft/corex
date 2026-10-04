<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Engine;

use Override;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;

/** Keeps every record, so tests can read what the engine logged. */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
    public array $records = [];

    public bool $failing = false;

    #[Override]
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if ($this->failing) {
            throw new RuntimeException('log transport down');
        }

        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
