<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Notifications\Delivery\Contracts\NotificationChannelDriver;
use CoreX\Notifications\Delivery\Data\ChannelDescriptor;
use CoreX\Notifications\Delivery\Data\DeliveryInput;
use CoreX\Notifications\Delivery\Data\DeliveryOutcome;
use CoreX\Notifications\Delivery\Enums\DeliveryOutcomeKind;

final readonly class FixtureNotificationDriver implements NotificationChannelDriver
{
    public const string CHANNEL = 'fixture';

    public function __construct(private ProviderSimulator $provider) {}

    public function descriptor(): ChannelDescriptor
    {
        $idempotent = ProviderSimulator::idempotent();

        return new ChannelDescriptor(self::CHANNEL, '1', $idempotent, ProviderSimulator::lookupEnabled(), $idempotent ? 600 : null, 'scenario.notifications');
    }

    public function send(DeliveryInput $input): DeliveryOutcome
    {
        $reply = $this->provider->send('notifications', $input->deliveryKey, $input->message->canonicalJson);

        if ($reply['status'] === 0) {
            ProviderSimulator::holdLostResponse('notifications', $input->deliveryKey);
        }

        return $this->outcome($reply);
    }

    public function reconcile(DeliveryInput $input): DeliveryOutcome
    {
        return $this->outcome($this->provider->lookup('notifications', $input->deliveryKey));
    }

    private function outcome(array $reply): DeliveryOutcome
    {
        return match ($reply['status']) {
            200 => new DeliveryOutcome(DeliveryOutcomeKind::Completed, $reply['reference'], null, 'fixture.confirmed'),
            404 => new DeliveryOutcome(DeliveryOutcomeKind::Unknown, null, null, 'fixture.lookup-pending'),
            default => new DeliveryOutcome(DeliveryOutcomeKind::Unknown, null, null, 'fixture.ack-lost'),
        };
    }
}
