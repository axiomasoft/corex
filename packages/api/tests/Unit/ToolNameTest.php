<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Unit;

use CoreX\Api\Internal\ToolName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ToolNameTest extends TestCase
{
    public function test_wire_name_projects_canonical_code_and_version(): void
    {
        $this->assertSame('files_read__v1', ToolName::wireName('files.read', 'v1'));
    }

    public function test_rejects_invalid_canonical_codes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ToolName::assertCanonical('Files.read');
    }

    public function test_rejects_reserved_version_suffix_in_verb(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ToolName::assertCanonical('files.read__v2');
    }

    public function test_rejects_invalid_versions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ToolName::assertVersion('v0');
    }
}
