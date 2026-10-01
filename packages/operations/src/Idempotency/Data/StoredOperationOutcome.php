<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Data;

use CoreX\Attributes\Api;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Exceptions\ProblemRegistryException;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\OperationResult;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use InvalidArgumentException;
use ValueError;

/** Canonical transport-neutral operation result persisted for semantic replay. */
#[Api(since: '1.0')]
final readonly class StoredOperationOutcome
{
    private function __construct(
        public string $kind,
        /** JSON value captured at the integration boundary. */
        public ?JsonDocument $data,
        public ?string $errorCode,
        public ?int $errorStatus,
        public ?string $errorMessage,
        /** JSON value captured at the integration boundary. */
        public JsonDocument $errorDetails,
        public ?string $errorCategory,
        public bool $errorRetryable,
        public ?string $errorPath,
    ) {}

    /** Immutable JSON value. */
    public static function success(JsonDocument $data): self
    {
        return new self('success', JsonDocuments::nullable($data), null, null, null, JsonDocuments::capture([]), null, false, null);
    }

    public static function failure(ApiError $error): self
    {
        $descriptor = $error->errorDescriptor();

        return new self(
            kind: 'error',
            data: JsonDocuments::nullable(null),
            errorCode: $error->errorCode,
            errorStatus: $error->status,
            errorMessage: $error->getMessage(),
            errorDetails: JsonDocuments::capture(JsonDocuments::map($error->details)),
            errorCategory: $descriptor->category->value,
            errorRetryable: $error->retryable,
            errorPath: $error->path,
        );
    }

    public static function fromCanonicalJson(string $json, ProblemRegistry $problems): self
    {
        $payload = JsonCanonical::decodeObject($json);

        if ($payload === null || JsonCanonical::encode($payload) !== $json) {
            throw new InvalidArgumentException('Stored operation outcome must be a canonical JSON object.');
        }

        $kind = $payload['kind'] ?? null;
        $rootKeys = array_keys($payload);
        sort($rootKeys);

        if ($kind === 'success' && $rootKeys === ['data', 'kind'] && is_array($payload['data'] ?? null)) {
            return self::success(JsonDocuments::capture($payload['data']));
        }

        $error = $payload['error'] ?? null;

        if ($kind !== 'error' || $rootKeys !== ['error', 'kind'] || ! is_array($error) || array_is_list($error)) {
            throw new InvalidArgumentException('Stored operation outcome has an invalid shape.');
        }

        $errorKeys = array_keys($error);
        sort($errorKeys);

        if ($errorKeys !== ['category', 'code', 'details', 'message', 'path', 'retryable', 'status']) {
            throw new InvalidArgumentException('Stored operation error has an invalid shape.');
        }

        $code = $error['code'] ?? null;
        $status = $error['status'] ?? null;
        $message = $error['message'] ?? null;
        $details = $error['details'] ?? null;
        $category = $error['category'] ?? null;
        $retryable = $error['retryable'] ?? null;
        $path = $error['path'] ?? null;

        if (! is_string($code) || ! is_int($status) || ! is_string($message)
            || ! is_array($details) || ! is_string($category) || ! is_bool($retryable)
            || ($path !== null && ! is_string($path))) {
            throw new InvalidArgumentException('Stored operation error has an invalid shape.');
        }

        try {
            $identity = $problems->fromWire($code)->code;
            $occurrence = new ApiError($identity, $status, $message, JsonDocuments::capture($details), ErrorCategory::from($category), $retryable, $path);
            $problems->assertOccurrence($occurrence->errorDescriptor(), $status);
        } catch (ProblemRegistryException|InvalidArgumentException|ValueError) {
            return self::failure(new ApiError(CoreErrorCode::InternalError, 500, 'Operation could not be completed.', category: ErrorCategory::Internal));
        }

        return new self(
            kind: 'error',
            data: JsonDocuments::nullable(null),
            errorCode: $code,
            errorStatus: $status,
            errorMessage: $message,
            errorDetails: JsonDocuments::capture($details),
            errorCategory: ErrorCategory::from($category)->value,
            errorRetryable: $retryable,
            errorPath: $path,
        );
    }

    public function canonicalJson(): string
    {
        return JsonCanonical::encode($this->kind === 'success'
            ? ['data' => JsonDocuments::optionalArray($this->data), 'kind' => 'success']
            : [
                'error' => [
                    'category' => $this->errorCategory,
                    'code' => $this->errorCode,
                    'details' => JsonDocuments::map($this->errorDetails),
                    'message' => $this->errorMessage,
                    'path' => $this->errorPath,
                    'retryable' => $this->errorRetryable,
                    'status' => $this->errorStatus,
                ],
                'kind' => 'error',
            ]);
    }

    public function result(bool $replayed): OperationResult
    {
        if ($this->kind !== 'success' || $this->data === null) {
            throw new InvalidArgumentException('An error outcome cannot become an operation result.');
        }

        return new OperationResult(data: JsonDocuments::capture($this->data), replayed: $replayed);
    }

    public function error(ProblemRegistry $problems): ApiError
    {
        if ($this->kind !== 'error' || $this->errorCode === null || $this->errorStatus === null
            || $this->errorMessage === null || $this->errorCategory === null) {
            throw new InvalidArgumentException('A success outcome cannot become an API error.');
        }

        return new ApiError(
            errorCode: $problems->fromWire($this->errorCode)->code,
            status: $this->errorStatus,
            message: $this->errorMessage,
            details: JsonDocuments::capture($this->errorDetails),
            category: ErrorCategory::from($this->errorCategory),
            retryable: $this->errorRetryable,
            path: $this->errorPath,
        );
    }
}
