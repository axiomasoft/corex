<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Operations\Validation\Data\JsonValue;
use CoreX\Operations\Validation\Data\SchemaHandle;
use CoreX\Tools\Errors\Enums\ToolsErrorCode;

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
use Packman\Attributes\Internal;
use RuntimeException;
use Throwable;

/** Isolated validation-only worker entrypoint for bounded schema admission/validation. */
#[Internal]
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
            $request = json_decode($input, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($request)) {
                throw new RuntimeException('Worker request must be an object.');
            }
            $action = $request['action'] ?? '';

            if ($action === 'admit') {
                $handle = $validator->admit(
                    schema: new JsonObject(self::text($request['schema'] ?? null)),
                    profile: self::text($request['profile'] ?? null),
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
                $handleData = $request['handle'] ?? null;

                if (! is_array($handleData) || ! is_array($handleData['localResources'] ?? null)) {
                    throw new RuntimeException('Worker schema handle must contain resources.');
                }
                $resources = [];
                foreach ($handleData['localResources'] as $key => $resource) {
                    if (! is_string($key)) {
                        throw new RuntimeException('Worker resource keys must be named.');
                    }
                    $resources[$key] = self::text($resource);
                }
                $handle = new SchemaHandle(
                    digest: self::text($handleData['digest'] ?? null),
                    profile: self::text($handleData['profile'] ?? null),
                    localResources: $resources,
                );
                $validation = $validator->validate(
                    schema: $handle,
                    value: new JsonValue(self::text($request['value'] ?? null)),
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
        } catch (ToolSchemaFailure $exception) {
            self::respond(['ok' => false, 'error' => $exception->errorCode->value]);
        } catch (Throwable) {
            self::respond(['ok' => false, 'error' => ToolsErrorCode::SchemaLimit->value]);
        }
    }

    private static function text(mixed $value): string
    {
        if (! is_string($value)) {
            throw new RuntimeException('Worker value must be a string.');
        }

        return $value;
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
