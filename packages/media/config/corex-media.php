<?php

declare(strict_types=1);

return [
    'max_upload_bytes' => 10 * 1024 * 1024,
    'max_image_pixels' => 20_000_000,
    'purposes' => ['attachment'],
    'allowed_mime_types' => [
        'application/octet-stream',
        'image/jpeg',
        'image/png',
        'text/plain',
    ],
    'conversion_driver_tag' => 'corex.media.conversion-drivers',
    'conversion' => [
        'thumbnail' => [
            'max_width' => 4096,
            'max_height' => 4096,
            'max_pixels' => 20_000_000,
            'max_output_bytes' => 10 * 1024 * 1024,
        ],
    ],
];
