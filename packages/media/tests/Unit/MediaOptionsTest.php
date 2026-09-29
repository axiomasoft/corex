<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Media\Internal\Support\MediaOptions;

it('rejects a non-positive max upload bytes limit', function (int $bytes): void {
    try {
        MediaOptions::fromArray(['max_upload_bytes' => $bytes]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toContain('max_upload_bytes: must be a positive integer');
    }
})->with([0, -1]);

it('rejects an empty allowed mime type list', function (): void {
    try {
        MediaOptions::fromArray(['allowed_mime_types' => []]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toContain('allowed_mime_types: must not be empty');
    }
});
