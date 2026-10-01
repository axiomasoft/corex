<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookHttpResult;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WebhookHttpTransport
{
    public function send(ValidatedWebhookRequest $request): WebhookHttpResult;
}
