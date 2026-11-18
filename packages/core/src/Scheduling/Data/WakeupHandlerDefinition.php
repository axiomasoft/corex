<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Data;

use CoreX\Scheduling\Contracts\DueWakeupHandler;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class WakeupHandlerDefinition
{
    public const string TAG = 'corex.scheduling.wakeup-handlers';

    /** @var class-string<DueWakeupHandler> */
    public string $handler;

    public function __construct(
        public string $kind,
        public string $version,
        string $handler,
        public bool $acceptLegacyUnstamped = false,
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]{0,63}$/', $kind) !== 1) {
            throw new InvalidArgumentException('Wakeup handler kind must be a stable lower-case identifier.');
        }

        if ($version === '' || trim($version) !== $version) {
            throw new InvalidArgumentException('Wakeup handler version must be a non-empty stable reference.');
        }

        if (! is_a($handler, DueWakeupHandler::class, true)) {
            throw new InvalidArgumentException('Wakeup handler class must implement DueWakeupHandler.');
        }

        $this->handler = $handler;
    }
}
