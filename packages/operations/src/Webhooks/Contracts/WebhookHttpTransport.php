<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\Data\ValidatedWebhookRequest;
use CoreX\Api\Data\WebhookHttpResult;

interface WebhookHttpTransport
{
    public function send(ValidatedWebhookRequest $request): WebhookHttpResult;
}
