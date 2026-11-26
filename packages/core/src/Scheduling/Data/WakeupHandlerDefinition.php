<?php

declare(strict_types=1);

namespace CoreX\Scheduling\Data;

use CoreX\Scheduling\Contracts\DueWakeupHandler;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class WakeupHandlerDefinition
{
    public const string TAG = 'corex.scheduling.wakeup-handlers';

    /** @var class-string<DueWakeupHandler> */
    public string $handler;

    public function __construct(
        public string $kind,
        public string $version,
        string $handler,
    ) {
        if (preg_match('/^[a-z][a-z0-9._-]{0,63}$/D', $kind) !== 1) {
            throw new InvalidArgumentException('Wakeup handler kind must be a stable lower-case identifier.');
        }

        if ($version === '' || trim($version) !== $version || strlen($version) > 128) {
            throw new InvalidArgumentException('Wakeup handler version must be a non-empty stable reference.');
        }

        if (! is_a($handler, DueWakeupHandler::class, true)) {
            throw new InvalidArgumentException('Wakeup handler class must implement DueWakeupHandler.');
        }

        $this->handler = $handler;
    }
}
