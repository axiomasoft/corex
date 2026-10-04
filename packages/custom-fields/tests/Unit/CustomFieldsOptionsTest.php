<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\CustomFields\Internal\CustomFieldsOptions;

it('passes on an empty environment with the shipped composition and table names', function (): void {
    $options = CustomFieldsOptions::fromArray([]);

    expect($options->composition->storageDriver)->toBe('jsonb')
        ->and($options->composition->sources)->toBe(['static'])
        ->and($options->tables['documents'])->toBe('sys_attribute_documents')
        ->and($options->tables['schema_operations'])->toBe('sys_attribute_schema_operations');
});

it('accumulates composition and table-map violations in one failure', function (): void {
    try {
        CustomFieldsOptions::fromArray(['storage_driver' => 'redis', 'tables' => ['documents' => 7]]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe(['tables.documents: must be a string']);
    }

    try {
        CustomFieldsOptions::fromArray(['storage_driver' => 'redis']);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe(['storage_driver: must be jsonb or eav']);
    }
});

it('keeps an overridden table name for the table-identifier check at resolution time', function (): void {
    expect(CustomFieldsOptions::fromArray(['tables' => ['documents' => 'Bad-Name']])->tables['documents'])->toBe('Bad-Name');
});
