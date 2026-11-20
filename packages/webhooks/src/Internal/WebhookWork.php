<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Webhooks\Data\WebhookDelivery;
use Packman\Attributes\Internal;

#[Internal]
final class WebhookWork
{
    public const string Owner = 'webhooks.delivery';

    public static function key(DataScope $scope, string $subscription, string $source, string $event, string $replay): string
    {
        return hash('sha256', JsonCanonical::encode([
            WebhookDeliveryScope::logical($scope), $subscription, $source, $event, $replay,
        ]));
    }

    public static function digest(string $endpoint, string $bodyHash, string $topic, int $version, int $subscriptionRevision, string $major): string
    {
        return hash('sha256', JsonCanonical::encode([$endpoint, $bodyHash, $topic, $version, $subscriptionRevision, $major]));
    }

    public static function reference(string $id, string $key, string $digest): string
    {
        return $id.':'.hash('sha256', JsonCanonical::encode([$key, $digest]));
    }

    public static function deliveryDigest(WebhookDelivery $delivery): string
    {
        return self::digest(
            $delivery->endpointSnapshot, $delivery->bodyHash, $delivery->topic,
            $delivery->topicVersion, $delivery->subscriptionRevision, $delivery->major,
        );
    }

    public static function proof(DataScope $scope, WebhookDelivery $delivery): StoredWorkProof
    {
        $effectKey = self::key($scope, $delivery->subscriptionId, $delivery->eventSource, $delivery->eventId, $delivery->replayId);

        if (! hash_equals($delivery->effectKey, $effectKey)) {
            throw new ScopeContextMismatch('Stored webhook effect identity does not match its owner.');
        }

        return new StoredWorkProof(self::Owner, self::reference($delivery->id, $delivery->effectKey, self::deliveryDigest($delivery)), $delivery->scopeSeal);
    }

    public static function effect(DataScope $scope, WebhookDelivery $delivery): ProviderEffectRef
    {
        return new ProviderEffectRef(
            $scope->accountId, $scope->accountIncarnation, $scope->workspaceId, self::Owner,
            'webhook.'.hash('sha256', $delivery->endpointSnapshot), $delivery->effectKey,
        );
    }
}
