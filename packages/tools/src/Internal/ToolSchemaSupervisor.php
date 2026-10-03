<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;
use CoreX\Operations\Validation\Data\SchemaValidation;
use CoreX\Operations\Validation\Laravel\SchemaProfile;
use CoreX\Tools\Contracts\ToolSchemaValidator;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;
use JsonException;
use Override;

/** Subprocess supervisor enforcing validation-only memory and deadline bounds. */
final class ToolSchemaSupervisor implements ToolSchemaValidator
{
    private const int WORKER_STARTUP_MS = 5_000;

    private const int TERMINATE_MS = 50;

    public function __construct(
        private readonly ?string $phpBinary = null,
        private readonly ?int $startupMs = null,
    ) {}

    #[Override]
    public function admit(JsonObject $schema, string $profile): SchemaHandle
    {
        if (! OpisToolSchemaValidator::isAvailable()) {
            throw ToolSchemaFailure::unavailable();
        }

        $response = $this->dispatch([
            'action' => 'admit',
            'schema' => $schema->canonicalJson,
            'profile' => $profile,
        ]);

        if (! isset($response['ok']) || $response['ok'] !== true) {
            $error = $response['error'] ?? null;

            throw new ToolSchemaFailure(errorCode: $error === ToolsErrorCode::SchemaUnavailable->value ? ToolsErrorCode::SchemaUnavailable : ToolsErrorCode::SchemaLimit);
        }

        $handle = $response['handle'] ?? null;

        if (! $this->validHandle($handle)) {
            throw ToolSchemaFailure::limit();
        }

        return new SchemaHandle(
            digest: $handle['digest'],
            profile: $handle['profile'],
            localResources: $handle['localResources'],
        );
    }

    #[Override]
    public function validate(SchemaHandle $schema, JsonValue $value): SchemaValidation
    {
        if (! OpisToolSchemaValidator::isAvailable()) {
            throw ToolSchemaFailure::unavailable();
        }

        $response = $this->dispatch([
            'action' => 'validate',
            'handle' => [
                'digest' => $schema->digest,
                'profile' => $schema->profile,
                'localResources' => $schema->localResources,
            ],
            'value' => $value->canonicalJson,
        ]);

        if (! isset($response['ok']) || $response['ok'] !== true) {
            $error = $response['error'] ?? null;

            throw new ToolSchemaFailure(errorCode: $error === ToolsErrorCode::SchemaUnavailable->value ? ToolsErrorCode::SchemaUnavailable : ToolsErrorCode::SchemaLimit);
        }

        $validation = $response['validation'] ?? null;

        if (! $this->validValidation($validation)) {
            throw ToolSchemaFailure::limit();
        }

        return new SchemaValidation(
            valid: $validation['valid'],
            errors: $validation['errors'],
        );
    }

    /** @phpstan-assert-if-true array{digest: string, profile: string, localResources: array<string, string>} $handle */
    private function validHandle(mixed $handle): bool
    {
        if (! is_array($handle) || ! is_string($handle['digest'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $handle['digest']) !== 1
            || ($handle['profile'] ?? null) !== SchemaProfile::TOOL_SCHEMA_V1
            || ! is_array($handle['localResources'] ?? null)
            || count($handle['localResources']) > SchemaProfile::MAX_RESOURCES
            || ! is_string($handle['localResources']['#'] ?? null)) {
            return false;
        }

        foreach ($handle['localResources'] as $key => $resource) {
            if (! is_string($key) || ! is_string($resource)) {
                return false;
            }
        }

        return true;
    }

    /** @phpstan-assert-if-true array{valid: bool, errors: list<array{code: string, pointer: string}>} $validation */
    private function validValidation(mixed $validation): bool
    {
        if (! is_array($validation) || ! is_bool($validation['valid'] ?? null)
            || ! is_array($validation['errors'] ?? null) || ! array_is_list($validation['errors'])
            || count($validation['errors']) > SchemaProfile::MAX_ERRORS
            || ($validation['valid'] && $validation['errors'] !== [])) {
            return false;
        }

        foreach ($validation['errors'] as $error) {
            if (! is_array($error) || ! is_string($error['code'] ?? null) || ! is_string($error['pointer'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<array-key, mixed>
     */
    private function dispatch(array $request): array
    {
        $command = [
            $this->phpBinary ?? PHP_BINARY,
            '-d', 'memory_limit='.intdiv(SchemaProfile::WORKER_MEMORY_BYTES, 1024 * 1024).'M',
            __DIR__.'/ToolSchemaWorker.php',
        ];
        // A failed spawn is a typed startup failure even when a host observes suppressed warnings.
        set_error_handler(static function (): never {
            throw ToolSchemaFailure::unavailable();
        }, E_WARNING);

        try {
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        } finally {
            restore_error_handler();
        }

        if (! is_resource($process)) {
            throw ToolSchemaFailure::unavailable();
        }

        $failure = ToolsErrorCode::SchemaUnavailable;

        try {
            foreach ($pipes as $pipe) {
                stream_set_blocking($pipe, false);
            }
            $stdout = '';
            $deadline = hrtime(true) / 1_000_000 + ($this->startupMs ?? self::WORKER_STARTUP_MS);
            while (true) {
                $this->checkDeadline($deadline, $failure);
                $this->readOutput($pipes, $stdout, $failure);
                $this->checkDeadline($deadline, $failure);
                $newline = strpos($stdout, "\n");

                if ($newline !== false) {
                    if (substr($stdout, 0, $newline) !== 'ready') {
                        throw new ToolSchemaFailure(errorCode: $failure);
                    }
                    $stdout = substr($stdout, $newline + 1);

                    break;
                }

                // Reject a bad frame as soon as it cannot become the ready line.
                if (! str_starts_with("ready\n", $stdout) || feof($pipes[1])) {
                    throw new ToolSchemaFailure(errorCode: $failure);
                }
                $this->waitForIO($pipes);
            }

            $failure = ToolsErrorCode::SchemaLimit;
            // Request encoding and pipe backpressure consume the validation budget too.
            $deadline = hrtime(true) / 1_000_000 + SchemaProfile::WORKER_DEADLINE_MS;
            $input = json_encode($request, JSON_THROW_ON_ERROR);
            $offset = 0;
            while (true) {
                $this->checkDeadline($deadline, $failure);

                if (isset($pipes[0])) {
                    $written = @fwrite($pipes[0], substr($input, $offset, 65_536));

                    if ($written === false) {
                        throw new ToolSchemaFailure(errorCode: $failure);
                    }
                    $offset += $written;

                    if ($offset === strlen($input)) {
                        fclose($pipes[0]);
                        unset($pipes[0]);
                    }
                }
                $this->readOutput($pipes, $stdout, $failure);
                $status = proc_get_status($process);

                if (! $status['running'] && feof($pipes[1])) {
                    if ($status['exitcode'] !== 0 || isset($pipes[0])) {
                        throw new ToolSchemaFailure(errorCode: $failure);
                    }

                    break;
                }
                $this->waitForIO($pipes, writeInput: isset($pipes[0]));
            }

            $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
            $this->checkDeadline($deadline, $failure);

            if (! is_array($decoded) || ! is_bool($decoded['ok'] ?? null)
                || (! $decoded['ok'] && (! is_string($decoded['error'] ?? null) || $decoded['error'] === ''))) {
                throw new ToolSchemaFailure(errorCode: $failure);
            }

            return $decoded;
        } catch (JsonException) {
            throw new ToolSchemaFailure(errorCode: $failure);
        } finally {
            $this->cleanup($process, $pipes);
        }
    }

    private function checkDeadline(float $deadline, ToolsErrorCode $failure): void
    {
        if (hrtime(true) / 1_000_000 >= $deadline) {
            throw new ToolSchemaFailure(errorCode: $failure);
        }
    }

    /** @param array<int, resource> $pipes */
    private function readOutput(array $pipes, string &$stdout, ToolsErrorCode $failure): void
    {
        $chunk = @fread($pipes[1], 65_536);
        $stderr = @fread($pipes[2], 65_536);

        if ($chunk === false || $stderr === false) {
            throw new ToolSchemaFailure(errorCode: $failure);
        }
        $stdout .= $chunk;

        // Stderr is drained and discarded; neither pipe can grow the parent without bound.
        if (strlen($stdout) > SchemaProfile::WORKER_MEMORY_BYTES) {
            throw new ToolSchemaFailure(errorCode: $failure);
        }
    }

    /** @param array<int, resource> $pipes */
    private function waitForIO(array $pipes, bool $writeInput = false): void
    {
        $read = [$pipes[1], $pipes[2]];
        $write = $writeInput ? [$pipes[0]] : [];
        $except = [];
        @stream_select($read, $write, $except, 0, 1000);
    }

    /**
     * @param  resource  $process
     *
     * @phpstan-impure
     */
    private function isRunning($process): bool
    {
        return proc_get_status($process)['running'];
    }

    /**
     * @param  resource  $process
     * @param  array<int, resource>  $pipes
     */
    private function cleanup($process, array $pipes): void
    {
        if ($this->isRunning($process)) {
            proc_terminate($process);
            $deadline = hrtime(true) / 1_000_000 + self::TERMINATE_MS;
            while ($this->isRunning($process) && hrtime(true) / 1_000_000 < $deadline) {
                // Drain pending output while allowing the child to handle termination.
                @fread($pipes[1], 65_536);
                @fread($pipes[2], 65_536);
                $this->waitForIO($pipes);
            }

            if ($this->isRunning($process)) {
                proc_terminate($process, 9);
            }
        }
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($process);
    }
}
