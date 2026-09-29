<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookHttpResult;

interface WebhookHttpTransport
{
    public function send(ValidatedWebhookRequest $request): WebhookHttpResult;
}
