<?php

declare(strict_types=1);

use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Internal\FieldMessageContracts;
use CoreX\CustomFields\Internal\FieldMessageRegistry;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Tests\Support\ExtensionType;
use CoreX\CustomFields\Types\Contracts\FieldConverterProvider;
use CoreX\CustomFields\Types\Contracts\FieldValueConverter;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\CustomFields\Types\Data\FieldValue;
use CoreX\CustomFields\Types\Internal\RegisteredFieldTypes;
use CoreX\Messaging\Testing\MessagingFixtures;

it('encodes normalized third-party data on a distinct reversible wire representation', function (): void {
    $type = new RegisteredFieldTypes([new ExtensionType], [])->resolve(new FieldTypeId('test.serial'), 1);
    $normalized = $type->normalize(FieldInput::from(' one '), typedField('test.serial'));
    $codec = $type->definition()->codec;
    expect($codec->encode($normalized)->canonicalJson)->toBe('"wire:ONE"')
        ->and($codec->decode($codec->encode($normalized))->document->canonicalJson)->toBe('"ONE"')
        ->and($codec->decode($codec->encode(FieldValue::from(null)))->document->canonicalJson)->toBe('null');
});

it('refuses a duplicate external type and version during activation', function (): void {
    expect(fn () => new RegisteredFieldTypes([new ExtensionType, new ExtensionType], []))->toThrow(InvalidArgumentException::class, 'Duplicate');
});

it('refuses an external codec identity already assigned to another type', function (): void {
    expect(fn () => new RegisteredFieldTypes([new ExtensionType, new ExtensionType('test.other')], []))->toThrow(InvalidArgumentException::class, 'Duplicate field codec');
});

it('keeps multiple codec versions of one type individually addressable', function (): void {
    $registry = new RegisteredFieldTypes([new ExtensionType, new ExtensionType(version: 2)], []);
    expect($registry->resolve(new FieldTypeId('test.serial'), 2)->definition()->codecVersion)->toBe(2)
        ->and($registry->resolve(new FieldTypeId('test.serial'), 1)->definition()->codecVersion)->toBe(1);
});

it('rejects reserved unqualified type names supplied by a host', function (): void {
    expect(fn () => new RegisteredFieldTypes([new ExtensionType('text')], []))->toThrow(InvalidArgumentException::class);
});

it('fails closed for an unsupported codec version of a builtin', function (): void {
    expect(fn () => new RegisteredFieldTypes([], [])->resolve(new FieldTypeId('int'), 2))->toThrow(FieldFailure::class, 'field-type-unavailable');
});

it('fails closed for an unregistered type or converter', function (): void {
    $types = new RegisteredFieldTypes([], []);
    expect(fn () => $types->resolve(new FieldTypeId('missing.field'), 1))->toThrow(FieldFailure::class)
        ->and(fn () => $types->converter('missing.converter', 1))->toThrow(FieldFailure::class);
});

it('requires a pinned converter before decoding into a different type', function (): void {
    $types = new RegisteredFieldTypes([], []);
    expect(fn () => $types->convert(FieldInput::from('100')->document, typedField('text'), typedField('int'), null, null))->toThrow(InvalidArgumentException::class, 'versioned field converter');
});

it('normalizes converted output before encoding the pending slot', function (): void {
    $provider = new class implements FieldConverterProvider, FieldValueConverter
    {
        public function id(): string
        {
            return 'test.numeric';
        }

        public function version(): int
        {
            return 3;
        }

        public function converter(): FieldValueConverter
        {
            return $this;
        }

        public function convert(FieldValue $value, FieldDefinition $from, FieldDefinition $to): FieldValue
        {
            return FieldValue::from('00042.0');
        }
    };
    $registry = new RegisteredFieldTypes([], [$provider]);
    expect($registry->convert(FieldInput::from('x')->document, typedField('text'), typedField('decimal', ['scale' => 2]), 'test.numeric', 3)->canonicalJson)->toBe('"42.00"')
        ->and(fn () => new RegisteredFieldTypes([], [$provider, $provider]))->toThrow(InvalidArgumentException::class, 'duplicate field converter');
});

it('rejects duplicate durable message contracts at registration', function (): void {
    expect(fn () => new FieldMessageRegistry(MessagingFixtures::contracts(FieldMessageContracts::all())))->toThrow(InvalidArgumentException::class, 'Duplicate message contract');
});

it('exposes three exact contract names with a versioned schema and a pseudonymous PII bound', function (): void {
    $registry = new FieldMessageRegistry(MessagingFixtures::contracts([]));
    expect(array_map(fn ($contract) => $contract->id(), $registry->all()))->toBe(['fields.schema.changed.v2', 'fields.schema.requested.v2', 'fields.values.changed.v2']);
    foreach ($registry->all() as $contract) {
        expect($contract->dataSchema)->toBe('urn:corex:schema:'.$contract->id())->and($contract->maxPiiClass->value)->toBe('pseudonymous');
    }
});
