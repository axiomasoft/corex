<?php

declare(strict_types=1);

namespace CoreX\Tools\Tests\Unit;

use CoreX\Operations\Validation\Laravel\SchemaProfile;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use CoreX\Tools\Internal\ToolSchemaWorker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercise the real CLI framing and validator on an isolated input stream in the covered process. */
final class ToolSchemaWorkerContractTest extends TestCase
{
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_framed_as_a_safe_coded_response(string $input, string $error): void
    {
        self::assertSame(['ok' => false, 'error' => $error], $this->request($input));
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidInputs(): iterable
    {
        yield 'empty' => ['', 'empty_request'];
        yield 'whitespace' => [" \n\t", 'empty_request'];
        yield 'unknown action' => ['{"action":"unknown"}', 'unknown_action'];
        yield 'missing action' => ['{}', 'unknown_action'];
        foreach ([
            '{broken', '"text"', 'null',
            '{"action":"admit","schema":[],"profile":"tool-schema-v1"}',
            '{"action":"admit","schema":"{}","profile":false}',
            '{"action":"admit","schema":"{}","profile":"unknown"}',
            '{"action":"validate","handle":null,"value":"{}"}',
            '{"action":"validate","handle":{"localResources":null},"value":"{}"}',
            '{"action":"validate","handle":{"localResources":["{}"]},"value":"{}"}',
            '{"action":"validate","handle":{"localResources":{"#":[]}},"value":"{}"}',
        ] as $index => $input) {
            yield 'malformed '.$index => [$input, ToolsErrorCode::SchemaLimit->value];
        }
    }

    public function test_admission_and_validation_keep_the_entire_worker_wire_contract(): void
    {
        $schema = '{"type":"object","properties":{"count":{"type":"integer"}},"required":["count"],"additionalProperties":false}';
        $admitted = $this->request(json_encode(['action' => 'admit', 'schema' => $schema, 'profile' => SchemaProfile::TOOL_SCHEMA_V1], JSON_THROW_ON_ERROR));
        self::assertTrue($admitted['ok']);
        self::assertIsArray($admitted['handle']);
        self::assertSame(['digest', 'profile', 'localResources'], array_keys($admitted['handle']));
        self::assertSame(SchemaProfile::TOOL_SCHEMA_V1, $admitted['handle']['profile']);
        self::assertIsString($admitted['handle']['digest']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/D', $admitted['handle']['digest']);
        self::assertIsArray($admitted['handle']['localResources']);
        self::assertArrayHasKey('#', $admitted['handle']['localResources']);

        $request = ['action' => 'validate', 'handle' => $admitted['handle'], 'value' => '{"count":7}'];
        self::assertSame(['ok' => true, 'validation' => ['valid' => true, 'errors' => []]], $this->request(json_encode($request, JSON_THROW_ON_ERROR)));
        $request['value'] = '{"count":"invalid"}';
        $invalid = $this->request(json_encode($request, JSON_THROW_ON_ERROR));
        self::assertTrue($invalid['ok']);
        self::assertIsArray($invalid['validation']);
        self::assertFalse($invalid['validation']['valid']);
        self::assertSame([
            ['code' => '', 'pointer' => ''],
            ['code' => 'properties', 'pointer' => ''],
            ['code' => 'additionalProperties', 'pointer' => ''],
            ['code' => 'type', 'pointer' => '/count'],
        ], $invalid['validation']['errors']);
        $request['value'] = null;
        self::assertSame(['ok' => false, 'error' => ToolsErrorCode::SchemaLimit->value], $this->request(json_encode($request, JSON_THROW_ON_ERROR)));
    }

    /** @return array<string, mixed> */
    private function request(string $input): array
    {
        $name = 'CoreX\\Tools\\Internal\\STDIN';

        if (! defined($name)) {
            $stream = fopen('php://temp', 'r+');
            self::assertIsResource($stream);
            define($name, $stream);
        }
        $stream = constant($name);
        self::assertIsResource($stream);
        self::assertTrue(ftruncate($stream, 0));
        rewind($stream);
        fwrite($stream, $input);
        rewind($stream);
        ob_start();

        try {
            ToolSchemaWorker::runCli();
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertIsString($output);
        self::assertStringStartsWith("ready\n", $output);
        $payload = json_decode(substr($output, 6), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }
}
