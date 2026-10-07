<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Unit;

use CoreX\Data\Data\JsonDocument;
use CoreX\Tools\Data\ToolOutcome;
use CoreX\Tools\Data\ToolResult;
use PHPUnit\Framework\TestCase;

final class ToolResultDefaultTest extends TestCase
{
    public function test_omitted_metadata_is_an_empty_list_and_explicit_metadata_is_preserved(): void
    {
        $default = new ToolResult(outcome: ToolOutcome::Committed, structuredData: null, errorCode: null, operationKey: null, replayed: false);
        self::assertSame('[]', $default->metadata->canonicalJson);
        self::assertFalse($default->replayed);
        $metadata = new JsonDocument('{"correlation":"request-1"}');
        $explicit = new ToolResult(outcome: ToolOutcome::Committed, structuredData: null, errorCode: null, operationKey: 'op-1', replayed: true, metadata: $metadata);
        self::assertSame($metadata, $explicit->metadata);
        self::assertTrue($explicit->replayed);
        self::assertSame('op-1', $explicit->operationKey);
    }
}
