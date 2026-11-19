<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Unit;

use Composer\Autoload\ClassLoader;
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

    public function test_split_worker_bootstraps_the_supplied_host_autoloader(): void
    {
        $temporary = sys_get_temp_dir().'/corex-split-worker-'.bin2hex(random_bytes(8));
        mkdir($temporary, 0700);
        $worker = $temporary.'/ToolSchemaWorker.php';
        $vendorDir = array_key_first(ClassLoader::getRegisteredLoaders());
        self::assertIsString($vendorDir);

        try {
            copy(__DIR__.'/../../src/Internal/ToolSchemaWorker.php', $worker);
            $process = new Process([PHP_BINARY, $worker, $vendorDir.'/autoload.php']);
            $process->setInput(json_encode([
                'action' => 'admit',
                'schema' => '{"type":"object"}',
                'profile' => SchemaProfile::TOOL_SCHEMA_V1,
            ], JSON_THROW_ON_ERROR));
            $process->mustRun();
            $frames = explode("\n", $process->getOutput(), 2);
            self::assertSame('ready', $frames[0]);
            $response = json_decode($frames[1], true, flags: JSON_THROW_ON_ERROR);
            self::assertTrue($response['ok']);
            self::assertSame(64, strlen($response['handle']['digest']));
        } finally {
            unlink($worker);
            rmdir($temporary);
        }
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
