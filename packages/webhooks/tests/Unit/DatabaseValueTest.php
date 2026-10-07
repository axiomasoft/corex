<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Unit;

use CoreX\Webhooks\Internal\DatabaseValue as WebhookDatabaseValue;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseValueTest extends TestCase
{
    public function test_large_database_counters_and_nullable_columns_are_lossless(): void
    {
        foreach ([WebhookDatabaseValue::class] as $adapter) {
            self::assertSame(PHP_INT_MAX, $adapter::integer((string) PHP_INT_MAX));
            self::assertSame(9007199254740992, $adapter::integer('9007199254740992'));
            self::assertSame(PHP_INT_MIN, $adapter::integer((string) PHP_INT_MIN));
            self::assertSame('0012', $adapter::text('0012'));
            self::assertNull($adapter::field((object) ['finished_at' => null], 'finished_at'));
        }
    }

    public function test_malformed_database_counters_cannot_be_coerced(): void
    {
        foreach ([WebhookDatabaseValue::class] as $adapter) {
            foreach ([true, null, [], 1.5, '1.5', '9223372036854775808'] as $value) {
                try {
                    $adapter::integer($value);
                    self::fail('Malformed database counter was accepted.');
                } catch (RuntimeException $error) {
                    self::assertStringContainsString('integer', $error->getMessage());
                }
            }
        }
    }

    public function test_missing_database_column_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing database column');
        WebhookDatabaseValue::field((object) [], 'scope');
    }
}
