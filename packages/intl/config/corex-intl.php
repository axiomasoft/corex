<?php

declare(strict_types=1);

return [
    'locale' => [
        'supported_tags' => ['en', 'en-US', 'ru', 'ru-RU'],
        'app_tag' => 'en',
        'explicit_fallback_tags' => ['en'],
        'allow_parent' => true,
        'settings_namespace' => 'intl',
        'settings_key' => 'locale',
        'max_supported_tags' => 100,
        'max_tag_length' => 128,
    ],
    'money' => [
        'metadata_version' => 'brick/1',
        'default_source' => 'manual',
        'batch_limit' => 1000,
    ],
    'reference' => [
        'dataset_key' => 'geography',
        'cursor_secret' => env('COREX_INTL_CURSOR_SECRET', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'),
        'providers' => [
            'boxes' => ['fixture' => __DIR__.'/../tests/fixtures/reference/boxes.json'],
            'compact' => ['fixture' => __DIR__.'/../tests/fixtures/reference/compact.json'],
        ],
    ],
    'formatting' => [
        'icu_enabled' => extension_loaded('intl'),
    ],
];
