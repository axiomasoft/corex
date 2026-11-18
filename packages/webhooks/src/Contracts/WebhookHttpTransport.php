<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Contracts;

use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookHttpResult;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface WebhookHttpTransport
{
    public function send(ValidatedWebhookRequest $request): WebhookHttpResult;
}
