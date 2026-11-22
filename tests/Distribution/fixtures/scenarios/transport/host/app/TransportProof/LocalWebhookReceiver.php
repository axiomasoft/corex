<?php

declare(strict_types=1);

namespace App\TransportProof;

use App\Scenario\FixtureWebhookTransport;
use App\Scenario\Scenario;
use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookHttpResult;
use RuntimeException;

final class LocalWebhookReceiver implements WebhookHttpTransport
{
    public function send(ValidatedWebhookRequest $request): WebhookHttpResult
    {
        if ($request->canonicalHost !== FixtureWebhookTransport::HOST || $request->address !== '93.184.216.34' || $request->effectKey === null) {
            throw new RuntimeException('Installed Webhooks did not bind the declared owner destination and effect.');
        }
        $headers = $request->signatureHeaders;
        $expected = 'v1,'.base64_encode(hash_hmac('sha256', $headers['webhook-id'].'.'.$headers['webhook-timestamp'].'.'.$request->body, FixtureWebhookTransport::secret(), true));

        if (! hash_equals($expected, $headers['webhook-signature'])) {
            throw new RuntimeException('The wire signature does not cover the received bytes.');
        }

        $connection = Scenario::connection('control');
        $connection->table('installed_webhook_receiver')->insert([
            'effect_key' => $request->effectKey,
            'delivery_ref' => $headers['webhook-id'],
            'wire_sha256' => hash('sha256', $request->body),
            'signature_sha256' => hash('sha256', $headers['webhook-signature']),
            'backend_pid' => Scenario::describe($connection)['session'],
        ]);

        return new WebhookHttpResult(204, null, null, 0, providerRef: $request->effectKey);
    }
}
