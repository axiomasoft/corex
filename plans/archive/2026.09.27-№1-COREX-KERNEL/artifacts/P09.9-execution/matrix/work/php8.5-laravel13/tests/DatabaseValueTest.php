<?php

declare(strict_types=1);

use CoreX\Tenancy\Provisioning\Internal\DatabaseValue;

it('keeps native-range PDO integers and unsigned64 counters exact', function (): void {
    expect(DatabaseValue::integer((string) PHP_INT_MAX))->toBe(PHP_INT_MAX)
        ->and(DatabaseValue::integer((string) PHP_INT_MIN))->toBe(PHP_INT_MIN)
        ->and(DatabaseValue::integer(5432))->toBe(5432)
        ->and(DatabaseValue::decimal('18446744073709551615'))->toBe('18446744073709551615')
        ->and(DatabaseValue::decimal(0))->toBe('0');
});

it('refuses lossy integer conversion at the database boundary', function (mixed $value): void {
    expect(fn () => DatabaseValue::integer($value))->toThrow(RuntimeException::class);
})->with([
    'native overflow' => ['9223372036854775808'],
    'native underflow' => ['-9223372036854775809'],
    'decimal fraction' => ['1.5'],
    'boolean authority value' => [true],
    'floating point value' => [1.0],
    'array' => [[]],
    'null' => [null],
]);

it('refuses coercion of routing text and missing result rows', function (): void {
    expect(DatabaseValue::text('acc_123_test'))->toBe('acc_123_test')
        ->and(fn () => DatabaseValue::text(123))->toThrow(RuntimeException::class)
        ->and(fn () => DatabaseValue::text(null))->toThrow(RuntimeException::class)
        ->and(fn () => DatabaseValue::row(null))->toThrow(RuntimeException::class)
        ->and(fn () => DatabaseValue::decimal(1.5))->toThrow(RuntimeException::class)
        ->and(fn () => DatabaseValue::decimal('-1'))->toThrow(RuntimeException::class);
});
