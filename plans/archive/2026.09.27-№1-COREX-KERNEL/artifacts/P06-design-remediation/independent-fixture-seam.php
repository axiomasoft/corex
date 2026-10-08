<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Enums {
    enum WebhookFailureReason: string
    {
        case HttpPermanent = 'http_permanent';
    }
}

namespace CoreX\Webhooks\Data {
    use CoreX\Webhooks\Enums\WebhookFailureReason;

    // Isolated representation of D92's new DTO signature; product is not modified.
    final readonly class WebhookHttpResult
    {
        public function __construct(public ?int $status, public ?string $retryAfter, public ?WebhookFailureReason $errorCode, public int $durationMs) {}
    }
}

namespace {
    use CoreX\Webhooks\Data\ValidatedWebhookRequest;
    use CoreX\Webhooks\Data\WebhookHttpResult;
    use CoreX\Webhooks\Tests\Fixtures\LocalWebhookReceiver;

    require getcwd().'/vendor/autoload.php';
    $receiver = new LocalWebhookReceiver([], 1);
    $request = new ValidatedWebhookRequest(
        canonicalHost: 'hooks.example', url: 'https://hooks.example/receiver', address: '8.8.8.8', body: '{}',
        signatureHeaders: ['webhook-id' => 'event-1', 'webhook-timestamp' => '1', 'webhook-signature' => 'v1,invalid'],
    );

    try {
        $receiver->send($request);

        throw new RuntimeException('Expected existing fixture to expose its incompatible string');
    } catch (TypeError $error) {
        echo 'Unmigrated current fixture: '.$error->getMessage().PHP_EOL;
    }
    $corrected = new WebhookHttpResult(401, null, null, 0);
    echo 'Required future HTTP-only rejection: status='.$corrected->status.', errorCode='.json_encode($corrected->errorCode).PHP_EOL;
}
