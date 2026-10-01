<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use InvalidArgumentException;
use JsonException;

#[Api(since: '1.0')]
final readonly class BulkOperationDefinition
{
    /**
     * @param  array<string, scalar|null>  $encodedScope
     */
    public function __construct(
        public string $id,
        public string $submissionId,
        public string $operationId,
        public string $major,
        public string $contractHash,
        public array $encodedScope,
        public string $actorId,
        public string $clientId,
        public JsonDocument $extensionStamp,
        public JsonDocument $inputRef,
    ) {
        self::assertString($id, 'id', 64);
        self::assertString($submissionId, 'submission id', 64);
        self::assertString($operationId, 'operation id', 191);
        self::assertString($major, 'major', 32);
        self::assertString($actorId, 'actor id', 191);
        self::assertString($clientId, 'client id', 191);

        if (preg_match('/\A[a-f0-9]{64}\z/D', $contractHash) !== 1) {
            throw new InvalidArgumentException('Bulk contract hash must be a lowercase SHA-256 digest.');
        }

        if ($encodedScope === []) {
            throw new InvalidArgumentException('Bulk encoded scope must be a non-empty JSON object.');
        }

        self::assertJsonObject(JsonDocuments::map($extensionStamp), 'extension stamp');
        self::assertJsonObject(JsonDocuments::map($inputRef), 'input reference');
        self::assertSafeInputReference(JsonDocuments::map($inputRef));
    }

    private static function assertString(string $value, string $name, int $maximum): void
    {
        if ($value === '' || strlen($value) > $maximum || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            throw new InvalidArgumentException("Bulk {$name} must contain 1-{$maximum} safe bytes.");
        }
    }

    /** @param array<string, mixed> $value */
    private static function assertJsonObject(array $value, string $name): void
    {
        if ($value === []) {
            throw new InvalidArgumentException("Bulk {$name} must be a non-empty JSON object.");
        }

        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("Bulk {$name} must be JSON encodable.", previous: $exception);
        }
    }

    /** @param array<string, mixed> $inputRef */
    private static function assertSafeInputReference(array $inputRef): void
    {
        try {
            $encoded = json_encode($inputRef, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Bulk input reference must be JSON encodable.', previous: $exception);
        }

        if (strlen($encoded) > 20 * 1024) {
            throw new InvalidArgumentException('Bulk input reference must not exceed 20 KiB.');
        }

        self::assertNoUnsafeInput($inputRef);
    }

    /** @param array<array-key, mixed> $value */
    private static function assertNoUnsafeInput(array $value): void
    {
        $forbiddenKeys = ['url', 'path', 'credentials', 'secret', 'serialized_context', 'serialized_model'];

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), $forbiddenKeys, true)) {
                throw new InvalidArgumentException('Bulk input reference must not contain URLs, paths, credentials, secrets or serialized runtime state.');
            }

            if (is_array($item)) {
                self::assertNoUnsafeInput($item);

                continue;
            }

            if (is_object($item) || is_resource($item)) {
                throw new InvalidArgumentException('Bulk input reference must contain only JSON values.');
            }

            if (is_string($item) && (
                preg_match('/\A(?:https?|file):\/\//i', $item) === 1
                || str_starts_with($item, '/')
                || preg_match('/\A[A-Za-z]:[\\\\\/]/', $item) === 1
                || preg_match('/\AO:\d+:"/', $item) === 1
            )) {
                throw new InvalidArgumentException('Bulk input reference must not contain URLs, disk paths or serialized models.');
            }
        }
    }
}
