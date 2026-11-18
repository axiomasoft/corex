<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal\Http;

use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Packman\Attributes\Internal;

/** Projects async Location and deprecation headers from the operation descriptor. */
#[Internal]
final class HttpOperationResponder
{
    public function project(?Operation $operation, JsonResponse $response): JsonResponse
    {
        if (! $operation instanceof Operation || $response->isClientError() || $response->isServerError()) {
            return $response;
        }

        if ($operation->mode === OperationMode::AsyncCommand && $response->getStatusCode() === 202) {
            $response->header('Location', $this->location($response));
        }

        $deprecation = $operation->deprecation;

        if ($deprecation === null) {
            return $response;
        }

        $response->header('Deprecation', $this->httpDate(is_string($deprecation['deprecatedAt'] ?? null) ? $deprecation['deprecatedAt'] : ''));

        if (is_string($deprecation['sunsetAt'] ?? null) && $deprecation['sunsetAt'] !== '') {
            $response->header('Sunset', $this->httpDate($deprecation['sunsetAt']));
        }

        if (is_string($deprecation['migrationUri'] ?? null) && $deprecation['migrationUri'] !== '') {
            $response->header('Link', '<'.$deprecation['migrationUri'].'>; rel="deprecation"');
        }

        if (is_string($deprecation['successor'] ?? null) && $deprecation['successor'] !== '') {
            $response->header('successor-version', $deprecation['successor']);
        }

        return $response;
    }

    private function location(JsonResponse $response): string
    {
        $payload = $response->getData(true);
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;
        $operationId = is_array($data) ? ($data['operation_id'] ?? null) : null;
        $statusUrl = is_array($data) ? ($data['status_url'] ?? null) : null;

        if (! is_string($operationId) || $operationId === '' || ! is_string($statusUrl) || preg_match('#\A(?:https://\S+|/[^\s]*)\z#', $statusUrl) !== 1) {
            throw new ApiError(errorCode: OperationsErrorCode::InvalidRequest, status: 422, message: 'Async acceptance is incomplete.');
        }

        return $statusUrl;
    }

    private function httpDate(string $day): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));

        if ($parsed === false || $parsed->format('Y-m-d') !== $day) {
            throw new ApiError(errorCode: CoreErrorCode::InternalError, status: 500, message: 'Operation could not be completed.');
        }

        return $parsed->format('D, d M Y H:i:s').' GMT';
    }
}
