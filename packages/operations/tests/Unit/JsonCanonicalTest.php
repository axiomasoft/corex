<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Operations\Validation\Laravel\JsonCanonical;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonCanonicalTest extends TestCase
{
    public function test_numeric_member_names_and_nested_schema_lists_are_preserved(): void
    {
        $json = '{"12":"value","name":{"0":false,"kind":"object"},"items":[1,null]}';
        $expected = [12 => 'value', 'name' => [0 => false, 'kind' => 'object'], 'items' => [1, null]];

        self::assertSame($expected, JsonCanonical::decode($json));
        self::assertSame($expected, JsonCanonical::decodeObject($json));
        self::assertSame([12 => 'value', 'items' => [1, null], 'name' => [0 => false, 'kind' => 'object']], JsonCanonical::decode(JsonCanonical::encode($expected)));
    }

    public function test_escaped_duplicate_numeric_member_names_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate JSON object key');
        JsonCanonical::decode('{"12":1,"\\u0031\\u0032":2}');
    }

    #[DataProvider('values')]
    public function test_scalar_and_list_values_keep_their_runtime_types(string $json, mixed $expected): void
    {
        self::assertSame($expected, JsonCanonical::decode($json));
    }

    public static function values(): array
    {
        return [['null', null], ['true', true], ['"text"', 'text'], ['1', 1], ['1.5', 1.5], ['[1,"two"]', [1, 'two']]];
    }
}
