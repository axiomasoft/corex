<?php

declare(strict_types=1);

namespace CoreX\Notifications\Contracts;

use CoreX\Notifications\Data\ChannelDescriptor;
use CoreX\Notifications\Data\DeliveryInput;
use CoreX\Notifications\Data\DeliveryOutcome;

interface NotificationChannelDriver
{
    public function descriptor(): ChannelDescriptor;

    public function send(DeliveryInput $input): DeliveryOutcome;

    public function reconcile(DeliveryInput $input): DeliveryOutcome;
}
