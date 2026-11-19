<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Unit;

use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;
use CoreX\Operations\Validation\Laravel\SchemaProfile;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use CoreX\Tools\Internal\ToolSchemaFailure;
use CoreX\Tools\Internal\ToolSchemaSupervisor;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ToolSchemaSupervisorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/corex-schema-worker-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        parent::tearDown();
    }

    private function supervisor(string $mode, ?int $startupMs = null): ToolSchemaSupervisor
    {
        $fixture = realpath(__DIR__.'/../Stubs/schema-worker.php');
        $wrapper = $this->directory.'/php-worker';
        $code = '#!'.PHP_BINARY."\n<?php\n";
        $code .= '$args = array_slice($argv, 1, -1);'."\n";
        $code .= '$args[count($args) - 1] = '.var_export($fixture, true).";\n";
        $code .= '$args[] = '.var_export($mode, true).";\n";
        $code .= '$args[] = '.var_export($this->directory.'/pid', true).";\n";
        $code .= '$args[] = end($argv);'."\n";
        $code .= 'pcntl_exec(PHP_BINARY, $args);'."\nexit(1);\n";
        file_put_contents($wrapper, $code);
        chmod($wrapper, 0700);

        return new ToolSchemaSupervisor(phpBinary: $wrapper, startupMs: $startupMs);
    }

    private function assertWorkerStopped(): void
    {
        self::assertFileExists($this->directory.'/pid');
        $pid = (int) file_get_contents($this->directory.'/pid');
        self::assertGreaterThan(0, $pid);
        self::assertFalse(is_dir('/proc/'.$pid), 'Worker process was not reaped: '.$pid);
    }

    private function validate(ToolSchemaSupervisor $supervisor, string $value = '{}'): void
    {
        $result = $supervisor->validate(
            new SchemaHandle(str_repeat('a', 64), SchemaProfile::TOOL_SCHEMA_V1, ['#' => '{}']),
            new JsonValue($value),
        );
        self::assertTrue($result->valid);
        self::assertSame([], $result->errors);
    }

    public function test_slow_start_does_not_consume_validation_deadline(): void
    {
        $supervisor = $this->supervisor('slow-start');
        $handle = $supervisor->admit(new JsonObject('{"type":"object"}'), SchemaProfile::TOOL_SCHEMA_V1);
        self::assertSame(SchemaProfile::TOOL_SCHEMA_V1, $handle->profile);
        self::assertSame('{"type":"object"}', $handle->localResources['#']);
        $this->assertWorkerStopped();
    }

    #[DataProvider('successfulWorkers')]
    public function test_ready_framing_and_stderr_drain(string $mode): void
    {
        $this->validate($this->supervisor($mode));
        $this->assertWorkerStopped();
    }

    public static function successfulWorkers(): array
    {
        return [['partial-ready'], ['coalesced'], ['stderr']];
    }

    #[DataProvider('failedWorkers')]
    public function test_failures_are_closed_and_processes_reaped(string $mode, ToolsErrorCode $error): void
    {
        $supervisor = $this->supervisor($mode, startupMs: $mode === 'no-ready' ? 200 : null);

        try {
            $this->validate($supervisor, $mode === 'blocked-input' ? '"'.str_repeat('x', 256 * 1024).'"' : '{}');
            self::fail('Worker failure admitted a result.');
        } catch (ToolSchemaFailure $exception) {
            self::assertSame($error, $exception->errorCode);
        }
        $this->assertWorkerStopped();
    }

    public static function failedWorkers(): array
    {
        return [
            ['no-ready', ToolsErrorCode::SchemaUnavailable],
            ['bad-ready', ToolsErrorCode::SchemaUnavailable],
            ['eof', ToolsErrorCode::SchemaUnavailable],
            ['timeout', ToolsErrorCode::SchemaLimit],
            ['ignore-terminate', ToolsErrorCode::SchemaLimit],
            ['blocked-input', ToolsErrorCode::SchemaLimit],
            ['memory', ToolsErrorCode::SchemaLimit],
            ['malformed', ToolsErrorCode::SchemaLimit],
            ['truncated', ToolsErrorCode::SchemaLimit],
            ['wrong-shape', ToolsErrorCode::SchemaLimit],
            ['wrong-errors', ToolsErrorCode::SchemaLimit],
            ['missing-errors', ToolsErrorCode::SchemaLimit],
            ['contradictory', ToolsErrorCode::SchemaLimit],
            ['trailing', ToolsErrorCode::SchemaLimit],
            ['failed', ToolsErrorCode::SchemaLimit],
        ];
    }

    public function test_malformed_handle_is_rejected(): void
    {
        $supervisor = $this->supervisor('wrong-handle');

        try {
            $supervisor->admit(new JsonObject('{"type":"object"}'), SchemaProfile::TOOL_SCHEMA_V1);
            self::fail('Malformed handle admitted.');
        } catch (ToolSchemaFailure $exception) {
            self::assertSame(ToolsErrorCode::SchemaLimit, $exception->errorCode);
        }
        $this->assertWorkerStopped();
    }

    public function test_missing_executable_is_unavailable(): void
    {
        $this->expectException(ToolSchemaFailure::class);
        $this->expectExceptionMessage(ToolsErrorCode::SchemaUnavailable->value);
        set_error_handler(static function (): never {
            throw new LogicException('Spawn warning escaped the supervisor.');
        }, E_WARNING);

        try {
            $this->validate(new ToolSchemaSupervisor(phpBinary: $this->directory.'/missing'));
        } finally {
            restore_error_handler();
        }
    }
}
