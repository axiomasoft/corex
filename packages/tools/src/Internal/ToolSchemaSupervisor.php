<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;
use CoreX\Operations\Validation\Data\SchemaValidation;
use CoreX\Operations\Validation\SchemaProfile;
use CoreX\Tools\Contracts\ToolSchemaValidator;
use JsonException;
use Override;
use RuntimeException;

/** Subprocess supervisor enforcing validation-only memory and deadline bounds. */
final class ToolSchemaSupervisor implements ToolSchemaValidator
{
    public function __construct(
        private readonly ?string $phpBinary = null,
    ) {}

    #[Override]
    public function admit(JsonObject $schema, string $profile): SchemaHandle
    {
        if (! OpisToolSchemaValidator::isAvailable()) {
            throw new RuntimeException('schema_unavailable');
        }

        $response = $this->dispatch([
            'action' => 'admit',
            'schema' => $schema->canonicalJson,
            'profile' => $profile,
        ]);

        if (! isset($response['ok']) || $response['ok'] !== true) {
            throw new RuntimeException((string) ($response['error'] ?? 'schema_limit'));
        }

        if (! isset($response['handle']) || ! is_array($response['handle'])) {
            throw new RuntimeException('schema_limit');
        }

        $handle = $response['handle'];

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
            throw new RuntimeException('schema_unavailable');
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
            throw new RuntimeException((string) ($response['error'] ?? 'schema_limit'));
        }

        if (! isset($response['validation']) || ! is_array($response['validation'])) {
            throw new RuntimeException('schema_limit');
        }

        $validation = $response['validation'];

        return new SchemaValidation(
            valid: (bool) $validation['valid'],
            errors: $validation['errors'],
        );
    }

    /**
     * @param  array<string, mixed>  $request
     * @return array<string, mixed>
     */
    private function dispatch(array $request): array
    {
        $command = [
            $this->phpBinary ?? PHP_BINARY,
            '-d', 'memory_limit='.((int) (SchemaProfile::WORKER_MEMORY_BYTES / (1024 * 1024))).'M',
            __DIR__.'/ToolSchemaWorker.php',
        ];

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptor, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('schema_limit');
        }

        try {
            fwrite($pipes[0], json_encode($request, JSON_THROW_ON_ERROR));
            fclose($pipes[0]);

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $stdout = '';
            $deadline = microtime(true) + (SchemaProfile::WORKER_DEADLINE_MS / 1000);

            while (microtime(true) < $deadline) {
                $stdout .= stream_get_contents($pipes[1]) ?: '';
                $status = proc_get_status($process);

                if (! $status['running']) {
                    $stdout .= stream_get_contents($pipes[1]) ?: '';

                    break;
                }

                usleep(1000);
            }

            if (proc_get_status($process)['running']) {
                proc_terminate($process);

                throw new RuntimeException('schema_limit');
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            if ($stdout === '') {
                throw new RuntimeException('schema_limit');
            }

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (JsonException) {
            proc_terminate($process);

            throw new RuntimeException('schema_limit');
        }
    }
}
