<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\CustomFields\Internal\CustomFieldsOptions;

it('passes on an empty environment with the shipped composition', function (): void {
    $options = CustomFieldsOptions::fromArray([]);

    expect($options->composition->storageDriver)->toBe('jsonb')
        ->and($options->composition->sources)->toBe(['static']);
});

it('rejects an unsupported storage composition', function (): void {
    try {
        CustomFieldsOptions::fromArray(['storage_driver' => 'redis']);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe(['storage_driver: must be jsonb or eav']);
    }
});
