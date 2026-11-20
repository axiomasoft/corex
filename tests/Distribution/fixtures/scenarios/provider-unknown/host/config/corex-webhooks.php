<?php

declare(strict_types=1);

use App\Scenario\FixtureWebhookProjector;

return [
    'delivery_enabled' => true,
    'worker_ready' => true,
    'lease_seconds' => 15,
    'topics' => [[
        'name' => 'fixture.recordchanged',
        'version' => 1,
        'major' => 'v1',
        'schema' => ['record' => 'string'],
        'pii_class' => 'none',
        'projector_service' => 'fixture.webhooks',
    ]],
    'projectors' => ['fixture.webhooks' => FixtureWebhookProjector::class],
];
