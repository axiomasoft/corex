<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Unit;

use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;
use CoreX\Operations\Validation\Laravel\SchemaProfile;
use CoreX\Tools\Internal\ToolSchemaSupervisor;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        $code .= '$args = array_slice($argv, 1);'."\n";
        $code .= '$args[count($args) - 1] = '.var_export($fixture, true).";\n";
        $code .= '$args[] = '.var_export($mode, true).";\n";
        $code .= '$args[] = '.var_export($this->directory.'/pid', true).";\n";
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
    public function test_failures_are_closed_and_processes_reaped(string $mode, string $error): void
    {
        $supervisor = $this->supervisor($mode, startupMs: $mode === 'no-ready' ? 200 : null);

        try {
            $this->validate($supervisor, $mode === 'blocked-input' ? '"'.str_repeat('x', 256 * 1024).'"' : '{}');
            self::fail('Worker failure admitted a result.');
        } catch (RuntimeException $exception) {
            self::assertSame($error, $exception->getMessage());
        }
        $this->assertWorkerStopped();
    }

    public static function failedWorkers(): array
    {
        return [
            ['no-ready', 'schema_unavailable'],
            ['bad-ready', 'schema_unavailable'],
            ['eof', 'schema_unavailable'],
            ['timeout', 'schema_limit'],
            ['ignore-terminate', 'schema_limit'],
            ['blocked-input', 'schema_limit'],
            ['memory', 'schema_limit'],
            ['malformed', 'schema_limit'],
            ['truncated', 'schema_limit'],
            ['wrong-shape', 'schema_limit'],
            ['wrong-errors', 'schema_limit'],
            ['missing-errors', 'schema_limit'],
            ['contradictory', 'schema_limit'],
            ['trailing', 'schema_limit'],
            ['failed', 'schema_limit'],
        ];
    }

    public function test_malformed_handle_is_rejected(): void
    {
        $supervisor = $this->supervisor('wrong-handle');

        try {
            $supervisor->admit(new JsonObject('{"type":"object"}'), SchemaProfile::TOOL_SCHEMA_V1);
            self::fail('Malformed handle admitted.');
        } catch (RuntimeException $exception) {
            self::assertSame('schema_limit', $exception->getMessage());
        }
        $this->assertWorkerStopped();
    }

    public function test_missing_executable_is_unavailable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('schema_unavailable');
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
