<?php

declare(strict_types=1);

namespace CoreX\Notifications\Internal\Digest;

use Closure;
use CoreX\Wakeup\Contracts\WakeupHandler;
use CoreX\Wakeup\Data\WakeupDelivery;
use RuntimeException;

final class DigestWakeupAdapter implements WakeupHandler
{
    /** @param Closure(DigestTick): void $handle */
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
