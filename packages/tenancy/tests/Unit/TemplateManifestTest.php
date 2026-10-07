<?php

declare(strict_types=1);

use CoreX\Tenancy\Provisioning\Exceptions\TemplateIntegrityException;
use CoreX\Tenancy\Provisioning\Internal\Database\SchemaCatalogueSerializer;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateManifest;

it('rejects unnamed signed payload members instead of silently removing them', function (): void {
    expect(fn () => TemplateManifest::fromConfig([
        'payload' => [0 => 'unnegotiated', 'template_id' => 'template'],
        'signature' => 'AA',
        'trusted_key_id' => 'test',
    ]))->toThrow(TemplateIntegrityException::class);
});

it('rejects empty decoded signatures before calling sodium', function (): void {
    $manifest = TemplateManifest::fromConfig([
        'payload' => ['template_id' => 'template'],
        'signature' => '',
        'trusted_key_id' => 'test',
    ]);

    expect(fn () => $manifest->assertTrusted(
        trustedKeys: ['test' => 'AA'],
        serializer: new SchemaCatalogueSerializer,
    ))->toThrow(TemplateIntegrityException::class);
});
