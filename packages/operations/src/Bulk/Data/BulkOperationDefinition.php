<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use CoreX\Context\Data\OperationKey;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use InvalidArgumentException;
use JsonException;
use Packman\Attributes\Api;

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
        public int $rowLimit = 500,
        public int $timeLimitSeconds = 20,
    ) {
        if ($rowLimit < 1 || $rowLimit > 500 || $timeLimitSeconds < 1 || $timeLimitSeconds > 30) {
            throw new InvalidArgumentException('Invalid bulk execution bounds.');
        }

        self::assertString(value: $id, name: 'id', maximum: 64);
        self::assertString(value: $submissionId, name: 'submission id', maximum: 64);
        self::assertString(value: $operationId, name: 'operation id', maximum: 191);
        self::assertString(value: $major, name: 'major', maximum: 32);
        self::assertString(value: $actorId, name: 'actor id', maximum: 191);
        self::assertString(value: $clientId, name: 'client id', maximum: 191);

        if (preg_match('/\A[a-f0-9]{64}\z/D', $contractHash) !== 1) {
            throw new InvalidArgumentException('Bulk contract hash must be a lowercase SHA-256 digest.');
        }

        if ($encodedScope === []) {
            throw new InvalidArgumentException('Bulk encoded scope must be a non-empty JSON object.');
        }

        self::assertJsonObject(value: JsonDocuments::map($extensionStamp), name: 'extension stamp');
        self::assertJsonObject(value: JsonDocuments::map($inputRef), name: 'input reference');
        self::assertSafeInputReference(JsonDocuments::map($inputRef));
        self::assertSafeInputReference(JsonDocuments::map($extensionStamp));
    }

    public function context(DataScope $scope, ?OperationKey $key = null): BulkContext
    {
        $kind = JsonDocuments::map($this->inputRef)['kind'] ?? null;

        if (! is_string($kind) || preg_match('/\A[a-z][a-z0-9.-]{0,63}\z/D', $kind) !== 1) {
            throw new InvalidArgumentException('A declared bulk kind is required.');
        }

        return new BulkContext($scope, $this->id, $kind, $this->contractHash, $key ?? new OperationKey($this->id, 1), $this->clientId, $this->inputRef, $this->extensionStamp);
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
