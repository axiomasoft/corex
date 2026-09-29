<?php

declare(strict_types=1);

namespace CoreX\Operations\Idempotency\Data;

use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\OperationResult;
use CoreX\Operations\Validation\JsonCanonical;
use InvalidArgumentException;

/** Canonical transport-neutral operation result persisted for semantic replay. */
final readonly class StoredOperationOutcome
{
    private function __construct(
        public string $kind,
        /** @var array<string, mixed>|list<mixed>|null */
        public ?array $data,
        public ?string $errorCode,
        public ?int $errorStatus,
        public ?string $errorMessage,
        /** @var array<string, mixed> */
        public array $errorDetails,
        public ?string $errorCategory,
        public bool $errorRetryable,
        public ?string $errorPath,
    ) {}

    /** @param array<string, mixed>|list<mixed> $data */
    public static function success(array $data): self
    {
        return new self('success', $data, null, null, null, [], null, false, null);
    }

    public static function failure(ApiError $error): self
    {
        $descriptor = $error->errorDescriptor();

        return new self(
            kind: 'error',
            data: null,
            errorCode: $error->errorCode,
            errorStatus: $error->status,
            errorMessage: $error->getMessage(),
            errorDetails: $error->details,
            errorCategory: $descriptor->category->value,
            errorRetryable: $error->retryable,
            errorPath: $error->path,
        );
    }

    public static function fromCanonicalJson(string $json): self
    {
        $payload = JsonCanonical::decodeObject($json);

        if ($payload === null || JsonCanonical::encode($payload) !== $json) {
            throw new InvalidArgumentException('Stored operation outcome must be a canonical JSON object.');
        }

        $kind = $payload['kind'] ?? null;
        $rootKeys = array_keys($payload);
        sort($rootKeys);

        if ($kind === 'success' && $rootKeys === ['data', 'kind'] && is_array($payload['data'] ?? null)) {
            return self::success($payload['data']);
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

        return new self(
            kind: 'error',
            data: null,
            errorCode: $code,
            errorStatus: $status,
            errorMessage: $message,
            errorDetails: $details,
            errorCategory: ErrorCategory::from($category)->value,
            errorRetryable: $retryable,
            errorPath: $path,
        );
    }

    public function canonicalJson(): string
    {
        return JsonCanonical::encode($this->kind === 'success'
            ? ['data' => $this->data, 'kind' => 'success']
            : [
                'error' => [
                    'category' => $this->errorCategory,
                    'code' => $this->errorCode,
                    'details' => $this->errorDetails,
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

        return new OperationResult(data: $this->data, replayed: $replayed);
    }

    public function error(): ApiError
    {
        if ($this->kind !== 'error' || $this->errorCode === null || $this->errorStatus === null
            || $this->errorMessage === null || $this->errorCategory === null) {
            throw new InvalidArgumentException('A success outcome cannot become an API error.');
        }

        return new ApiError(
            errorCode: $this->errorCode,
            status: $this->errorStatus,
            message: $this->errorMessage,
            details: $this->errorDetails,
            category: ErrorCategory::from($this->errorCategory),
            retryable: $this->errorRetryable,
            path: $this->errorPath,
        );
    }
}
