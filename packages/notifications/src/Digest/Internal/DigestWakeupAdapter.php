<?php

declare(strict_types=1);

namespace CoreX\Notifications\Digest\Internal;

use Closure;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Data\WakeupDelivery;
use RuntimeException;

final class DigestWakeupAdapter implements DueWakeupHandler
{
    /** @param Closure(DigestTick):void $handle */
    public function __construct(private readonly Closure $handle) {}

    public function handle(WakeupDelivery $delivery): void
    {
        if ($delivery->request->kind !== 'notification.digest.v1' || $delivery->request->window === null) {
            throw new RuntimeException('Wakeup request is not a notification digest tick.');
        }

        ($this->handle)(new DigestTick(
            tenant: $delivery->request->context,
            window: $delivery->request->window,
            requestKey: $delivery->request->requestKey,
        ));
    }
}
