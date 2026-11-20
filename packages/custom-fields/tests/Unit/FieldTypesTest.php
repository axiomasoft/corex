<?php

declare(strict_types=1);

use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\CustomFields\Types\Data\FieldJson;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\CustomFields\Types\Internal\RegisteredFieldTypes;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Enums\FieldUse;

function typedField(string $type, array $limits = [], string $localization = 'none'): FieldDefinition
{
    return new FieldDefinition(new FieldId('01900000-0000-7000-8000-000000000001'), new EntityHandle('cas.equipment'), 'serial', new FieldTypeId($type), 'runtime', constraints: $limits, localizationPolicy: $localization);
}

it('round trips canonical values and explicit null through every builtin codec', function (string $id, mixed $input, mixed $expected): void {
    $field = typedField($id);
    $types = new RegisteredFieldTypes([], []);
    $type = $types->resolve($field->type, 1);
    $codec = $type->definition()->codec;
    $value = $type->normalize(FieldInput::from($input), $field);
    expect($codec->decode($codec->encode($value))->document->canonicalJson)->toBe(FieldJson::encode($expected))
        ->and($codec->decode($codec->encode($type->normalize(FieldInput::from(null), $field)))->document->canonicalJson)->toBe('null');
})->with([
    ['text', '100', '100'], ['int', 123, 123], ['decimal', '00099599999999999999999.0100', '99599999999999999999.01'], ['bool', false, false],
    ['date', '2024-02-29', '2024-02-29'], ['datetime', '2024-02-29T13:00:00+04:00', '2024-02-29T09:00:00.000000Z'],
    ['select', 'one', 'one'], ['multiselect', ['z', 'a'], ['a', 'z']],
    ['reference', ['entity' => 'eq.owner', 'id' => 'person1'], ['entity' => 'eq.owner', 'id' => 'person1']],
    ['file', ['entity' => 'files.upload', 'id' => 'f1'], ['entity' => 'files.upload', 'id' => 'f1']],
    ['money', ['amount' => '100000000000000000000.01', 'currency' => 'EUR'], ['amount' => '100000000000000000000.01', 'currency' => 'EUR']],
    ['url', 'https://example.com', 'https://example.com'], ['email', 'a@example.com', 'a@example.com'], ['phone', '+447000000001', '+447000000001'],
    ['json', ['x' => [true, false, null]], ['x' => [true, false, null]]],
]);

it('preserves exact JSON numbers, localized maps, decimal bounds, and independent length and currency constraints', function (): void {
    $types = new RegisteredFieldTypes([], []);
    $normalize = static function (FieldDefinition $field, mixed $value) use ($types) {
        return $types->resolve($field->type, 1)->normalize(FieldInput::from($value), $field)->document->canonicalJson;
    };
    expect(fn () => $normalize(typedField('text', ['minLength' => 4]), '100'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $normalize(typedField('decimal', ['min' => '9999999999999999999.01']), '9999999999999999999.00'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $normalize(typedField('money', ['currency' => 'EUR']), ['currency' => 'USD', 'amount' => '5']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $normalize(typedField('date'), '2023-02-29'))->toThrow(InvalidArgumentException::class)
        ->and($normalize(typedField('decimal', ['scale' => 3]), '-00.10'))->toBe('"-0.100"')
        ->and($normalize(typedField('text', localization: 'localized'), ['fr' => 'bonjour', 'en' => 'hello']))->toBe('{"en":"hello","fr":"bonjour"}');
    $json = new JsonDocument('{"z":900719925474099333333333333,"a":{}}');
    expect(FieldJson::canonical($json->canonicalJson))->toBe('{"a":{},"z":900719925474099333333333333}')
        ->and($types->resolve(new FieldTypeId('json'), 1)->normalize(new FieldInput($json), typedField('json'))->document)->toBe($json);
});

it('publishes only null and absence for localized slots and orders money only with a pinned currency', function (): void {
    $types = new RegisteredFieldTypes([], []);
    $localized = typedField('decimal', localization: 'localized');
    expect(array_column($types->resolve($localized->type, 1)->operators($localized)->operators, 'value'))->toBe(['is-null', 'is-absent']);
    foreach ([['USD', true], [null, false]] as [$currency,$ordered]) {
        $field = typedField('money', $currency === null ? [] : ['currency' => $currency]);
        expect(in_array('between', array_column($types->resolve($field->type, 1)->operators($field)->operators, 'value'), true))->toBe($ordered);
    }
});

it('computes declared uses from field policy, builtin support and currency or locale requirements', function (): void {
    $types = new RegisteredFieldTypes([], []);
    $field = typedField('money');
    $definition = $field->toArray();
    $definition['uses'] = ['sort', 'aggregate', 'read'];
    $multi = FieldDefinition::fromArray($definition);
    $money = $types->resolve($field->type, 1)->definition();
    expect($multi->permits(FieldUse::Sort, $money))->toBeFalse()
        ->and($multi->permits(FieldUse::Aggregate, $money))->toBeFalse()
        ->and($multi->permits(FieldUse::Read, $money))->toBeTrue();
    $definition['constraints'] = ['currency' => 'EUR'];
    expect(FieldDefinition::fromArray($definition)->permits(FieldUse::Sort, $money))->toBeTrue();
    $definition['localization_policy'] = 'localized';
    expect(FieldDefinition::fromArray($definition)->permits(FieldUse::Sort, $money))->toBeFalse();
});

it('retains exact nested JSON numbers when round-tripping localized values', function (): void {
    $field = typedField('json', localization: 'localized');
    $type = (new RegisteredFieldTypes([], []))->resolve($field->type, 1);
    $raw = new JsonDocument('{"en":{"z":900719925474099333333333333},"fr-CA":[1.0000000000000000000000001]}');
    $codec = $type->definition()->codec;
    expect($codec->decode($codec->encode($type->normalize(new FieldInput($raw), $field)))->document->canonicalJson)->toBe($raw->canonicalJson)
        ->and(fn () => $type->normalize(new FieldInput(new JsonDocument('{"invalid.key":123}')), $field))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $type->normalize(new FieldInput(new JsonDocument('[123]')), $field))->toThrow(InvalidArgumentException::class);
});
