<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Data\Data\DataScope;
use CoreX\Webhooks\Internal\WebhookDeliveryScope;
use CoreX\Webhooks\Internal\WebhookWork;

final class WebhookRows
{
    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function original(DataScope $scope, array $row, ?StoredWorkIssuer $issuer = null): array
    {
        $source = 'orders';
        $key = WebhookWork::key($scope, $row['subscription_id'], $source, $row['event_id'], 'original');
        $digest = WebhookWork::digest(
            $row['endpoint_snapshot'], $row['body_hash'], $row['topic'], $row['topic_version'], $row['subscription_revision'], $row['major'],
        );
        $proof = $issuer?->issue($scope, WebhookWork::Owner, WebhookWork::reference($row['id'], $key, $digest));

        return [
            ...WebhookDeliveryScope::logical($scope),
            ...WebhookDeliveryScope::fence($scope),
            'effect_key' => $key,
            'event_source' => $source,
            'replay_id' => 'original',
            'scope_seal' => $proof?->seal ?? str_repeat('a', 64),
            ...$row,
        ];
    }
}
