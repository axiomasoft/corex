<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use RuntimeException;

final readonly class FixtureWebhookTransport implements WebhookHttpTransport
{
    public const string HOST = 'fixtures.example.test';

    public const string KEY = 'fixture-current';

    public function __construct(private ProviderSimulator $provider) {}

    public static function secret(): string
    {
        return str_repeat('z', 32);
    }

    public function send(ValidatedWebhookRequest $request): WebhookHttpResult
    {
        if ($request->canonicalHost !== self::HOST || $request->address !== '93.184.216.34') {
            throw new RuntimeException('The installed URL policy did not pin the synthetic destination.');
        }
        $headers = $request->signatureHeaders;
        $expected = 'v1,'.base64_encode(hash_hmac('sha256', $headers['webhook-id'].'.'.$headers['webhook-timestamp'].'.'.$request->body, self::secret(), true));

        if (! hash_equals($expected, $headers['webhook-signature'])) {
            throw new RuntimeException('The installed webhook signer did not sign the committed request bytes.');
        }
        $headers['Content-Type'] = 'application/cloudevents+json';
        $wire = [];
        foreach ($headers as $name => $value) {
            $wire[] = $name.': '.$value;
        }
        $key = $request->effectKey ?? throw new RuntimeException('Webhook send did not supply its persisted effect key.');
        $reply = $this->provider->send('webhooks', $key, $request->body, $wire);

        if ($reply['status'] === 0) {
            return new WebhookHttpResult(null, null, WebhookFailureReason::AckUnknown, 0);
        }

        return new WebhookHttpResult($reply['status'], null, null, 0, providerRef: $reply['reference']);
    }
}
