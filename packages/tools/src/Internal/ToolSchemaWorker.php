<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;

foreach ([
    __DIR__.'/../../vendor/autoload.php',
    __DIR__.'/../../../../vendor/autoload.php',
] as $autoload) {
    if (is_file($autoload)) {
        require_once $autoload;

        break;
    }
}
use JsonException;
use RuntimeException;
use Throwable;

/** Isolated validation-only worker entrypoint for bounded schema admission/validation. */
final class ToolSchemaWorker
{
    public static function runCli(): void
    {
        $validator = new OpisToolSchemaValidator;
        echo "ready\n";
        fflush(STDOUT);

        $input = stream_get_contents(STDIN);

        if ($input === false || trim($input) === '') {
            self::respond(['ok' => false, 'error' => 'empty_request']);

            return;
        }

        try {
            /** @var array<string, mixed> $request */
            $request = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            $action = $request['action'] ?? '';

            if ($action === 'admit') {
                $handle = $validator->admit(
                    new JsonObject((string) $request['schema']),
                    (string) $request['profile'],
                );
                self::respond([
                    'ok' => true,
                    'handle' => [
                        'digest' => $handle->digest,
                        'profile' => $handle->profile,
                        'localResources' => $handle->localResources,
                    ],
                ]);

                return;
            }

            if ($action === 'validate') {
                /** @var array{digest: string, profile: string, localResources: array<string, string>} $handleData */
                $handleData = $request['handle'];
                $handle = new SchemaHandle(
                    digest: (string) $handleData['digest'],
                    profile: (string) $handleData['profile'],
                    localResources: $handleData['localResources'],
                );
                $validation = $validator->validate(
                    $handle,
                    new JsonValue((string) $request['value']),
                );
                self::respond([
                    'ok' => true,
                    'validation' => [
                        'valid' => $validation->valid,
                        'errors' => $validation->errors,
                    ],
                ]);

                return;
            }

            self::respond(['ok' => false, 'error' => 'unknown_action']);
        } catch (Throwable $exception) {
            self::respond([
                'ok' => false,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function respond(array $payload): void
    {
        try {
            echo json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Worker response encoding failed.');
        }
    }
}

if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    ToolSchemaWorker::runCli();
}
