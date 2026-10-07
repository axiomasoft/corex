<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Unit;

use CoreX\Operations\Validation\Laravel\SchemaProfile;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ToolSchemaWorkerTest extends TestCase
{
    #[DataProvider('malformedRequests')]
    public function test_malformed_process_request_returns_a_coded_failure(mixed $request): void
    {
        $worker = new Process([PHP_BINARY, __DIR__.'/../../src/Internal/ToolSchemaWorker.php']);
        $worker->setInput(json_encode($request, JSON_THROW_ON_ERROR));
        $worker->run();

        self::assertSame(0, $worker->getExitCode());
        self::assertSame('', $worker->getErrorOutput());
        self::assertSame("ready\n".json_encode(['ok' => false, 'error' => ToolsErrorCode::SchemaLimit->value], JSON_THROW_ON_ERROR), $worker->getOutput());
    }

    public static function malformedRequests(): array
    {
        return [
            ['text'],
            [['action' => 'admit', 'schema' => [], 'profile' => SchemaProfile::TOOL_SCHEMA_V1]],
            [['action' => 'admit', 'schema' => '{}', 'profile' => false]],
            [['action' => 'validate', 'handle' => 'invalid', 'value' => '{}']],
            [['action' => 'validate', 'handle' => ['digest' => 'digest', 'profile' => SchemaProfile::TOOL_SCHEMA_V1, 'localResources' => ['#' => []]], 'value' => '{}']],
        ];
    }
}
