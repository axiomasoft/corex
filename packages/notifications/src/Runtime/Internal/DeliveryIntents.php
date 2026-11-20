<?php

declare(strict_types=1);

namespace CoreX\Notifications\Runtime\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Notifications\Inbox\Exceptions\NotificationOperationConflict;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class DeliveryIntents
{
    public const string Owner = 'notifications.delivery';

    public function __construct(
        private ConnectionInterface $connection,
        private CurrentWorkScope $current,
        private DataScopeCodec $scopes,
        private StoredWorkIssuer $issuer,
        private ChannelDrivers $drivers,
        private ClockInterface $clock,
    ) {}

    public function add(DataScope $scope, ?string $notificationId, ?string $runId, string $channel, string $effectKey, string $message): void
    {
        if ($this->connection->transactionLevel() < 1) {
            throw new NotificationOperationConflict('Notification delivery intent requires its owner transaction.');
        }
        $this->current->assert($scope, $this->connection);
        $driver = $this->drivers->find($channel)?->descriptor();

        if ($driver === null) {
            throw new NotificationOperationConflict('Notification channel is not registered.');
        }

        $id = (string) Str::uuid7();
        $digest = hash('sha256', $message);
        $reference = self::reference($id, $effectKey, $digest, $channel, $driver->version, $notificationId, $runId);
        $proof = $this->issuer->issue($scope, self::Owner, $reference);
        $this->connection->table('ntf_deliveries')->insert([
            ...NotificationScope::logical($scope),
            ...NotificationScope::fence($scope),
            'id' => $id,
            'scope' => json_encode($this->scopes->encode($scope), JSON_THROW_ON_ERROR),
            'scope_seal' => $proof->seal,
            'notification_id' => $notificationId,
            'digest_run_id' => $runId,
            'channel' => $channel,
            'delivery_key' => $effectKey,
            'state' => 'pending',
            'claim_epoch' => '0',
            'attempts' => 0,
            'next_attempt' => $this->clock->now(),
            'admitted_revision' => '1',
            'message' => $message,
            'message_hash' => $digest,
            'template_version' => '1',
            'policy_version' => '1',
            'driver_version' => $driver->version,
        ]);
    }

    public static function reference(string $id, string $key, string $digest, string $channel, string $driverVersion, ?string $notificationId, ?string $runId): string
    {
        return $id.':'.hash('sha256', json_encode([$key, $digest, $channel, $driverVersion, $notificationId, $runId], JSON_THROW_ON_ERROR));
    }
}
