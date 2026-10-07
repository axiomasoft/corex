<?php

declare(strict_types=1);

use Flex\Crm\Internal\Value;

it('distinguishes a stored null from a missing field', function (): void {
    $row = (object) ['name' => null, 'count' => 0];

    expect(Value::field(row: $row, name: 'name'))->toBeNull()
        ->and(Value::field(row: $row, name: 'count'))->toBe(0)
        ->and(fn (): mixed => Value::field(row: $row, name: 'missing'))->toThrow(RuntimeException::class, 'Missing stored field: missing');
});

it('accepts canonical integers throughout the native range', function (): void {
    foreach ([0, 42, -42, PHP_INT_MIN, PHP_INT_MAX] as $integer) {
        expect(Value::integer(value: $integer))->toBe($integer)
            ->and(Value::integer(value: (string) $integer))->toBe($integer);
    }
});

it('rejects noncanonical integers and values outside the native range', function (mixed $value): void {
    expect(fn (): int => Value::integer(value: $value))->toThrow(RuntimeException::class, 'Expected a database integer within the native range.');
})->with([null, true, false, 1.5, '', '+1', '01', ' 1', '1 ', '1.0', '1e2', '1\n', (string) PHP_INT_MAX.'0', (string) PHP_INT_MIN.'0', [[]], [new stdClass]]);

it('accepts text and nullable text without coercing other scalar types', function (): void {
    $text = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'stored';
        }
    };

    expect(Value::text(value: $text))->toBe('stored')
        ->and(Value::text(value: 0))->toBe('0')
        ->and(Value::text(value: ''))->toBe('')
        ->and(Value::nullableText(value: null))->toBeNull()
        ->and(Value::nullableText(value: 'stored'))->toBe('stored');

    foreach ([true, 1.5, [], null, new stdClass] as $invalid) {
        expect(fn (): string => Value::text(value: $invalid))->toThrow(RuntimeException::class, 'Expected a text value.');
    }
});

it('preserves string keyed maps and validates every element in a list of maps', function (): void {
    $map = ['name' => null, 'nested' => ['value' => 42]];

    expect(Value::map(value: $map))->toBe($map)
        ->and(Value::map(value: []))->toBe([])
        ->and(Value::nullableMap(value: null))->toBeNull()
        ->and(Value::nullableMap(value: $map))->toBe($map)
        ->and(Value::maps(value: [$map, []]))->toBe([$map, []])
        ->and(Value::maps(value: []))->toBe([])
        ->and(fn (): array => Value::map(value: null))->toThrow(RuntimeException::class, 'Expected a map.')
        ->and(fn (): array => Value::map(value: [42]))->toThrow(RuntimeException::class, 'Expected string map keys.')
        ->and(fn (): array => Value::maps(value: null))->toThrow(RuntimeException::class, 'Expected a list of maps.')
        ->and(fn (): array => Value::maps(value: ['first' => $map]))->toThrow(RuntimeException::class, 'Expected a list of maps.')
        ->and(fn (): array => Value::maps(value: [$map, null]))->toThrow(RuntimeException::class, 'Expected a map.')
        ->and(fn (): array => Value::maps(value: [$map, [42]]))->toThrow(RuntimeException::class, 'Expected string map keys.');
});

it('requires native boolean values', function (): void {
    expect(Value::boolean(value: true))->toBeTrue()
        ->and(Value::boolean(value: false))->toBeFalse();

    foreach ([0, 1, 'true', '', null, []] as $invalid) {
        expect(fn (): bool => Value::boolean(value: $invalid))->toThrow(RuntimeException::class, 'Expected a boolean.');
    }
});
