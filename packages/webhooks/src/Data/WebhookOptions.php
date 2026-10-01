<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Data;

use CoreX\Attributes\Api;

/** Immutable webhook activation policy; it never contains secret material. */
#[Api(since: '1.0')]
final readonly class WebhookOptions
{
    /**
     * @param  list<string>  $organizationDeniedCidrs
     * @param  list<WebhookTopic>  $topics
     * @param  array<string, string>  $projectors
     */
    public function __construct(
        public bool $deliveryEnabled,
        public bool $workerReady,
        public int $leaseSeconds,
        public int $bodyRetentionSeconds,
        public int $historyRetentionSeconds,
        public int $replayHorizonSeconds,
        public array $organizationDeniedCidrs,
        public array $topics,
        public array $projectors,
    ) {}

}
