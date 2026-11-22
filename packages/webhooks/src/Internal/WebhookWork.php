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
            endpoint: $delivery->endpointSnapshot, bodyHash: $delivery->bodyHash, topic: $delivery->topic,
            version: $delivery->topicVersion, subscriptionRevision: $delivery->subscriptionRevision, major: $delivery->major,
        );
    }

    public static function proof(DataScope $scope, WebhookDelivery $delivery): StoredWorkProof
    {
        $effectKey = self::key(scope: $scope, subscription: $delivery->subscriptionId, source: $delivery->eventSource, event: $delivery->eventId, replay: $delivery->replayId);

        if (! hash_equals($delivery->effectKey, $effectKey)) {
            throw new ScopeContextMismatch('Stored webhook effect identity does not match its owner.');
        }

        return new StoredWorkProof(owner: self::Owner, reference: self::reference(id: $delivery->id, key: $delivery->effectKey, digest: self::deliveryDigest($delivery)), seal: $delivery->scopeSeal);
    }

    public static function effect(DataScope $scope, WebhookDelivery $delivery): ProviderEffectRef
    {
        return new ProviderEffectRef(
            accountId: $scope->accountId, accountIncarnation: $scope->accountIncarnation, workspaceId: $scope->workspaceId, owner: self::Owner,
            providerNamespace: 'webhook.'.hash('sha256', $delivery->endpointSnapshot), effectKey: $delivery->effectKey,
        );
    }
}
