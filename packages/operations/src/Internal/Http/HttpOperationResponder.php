<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal\Http;

use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\AcceptedOperation;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Packman\Attributes\Internal;

/** Projects async Location and deprecation headers from the operation descriptor. */
#[Internal]
final class HttpOperationResponder
{
    public function project(Operation $operation, JsonResponse $response, ?AcceptedOperation $accepted): JsonResponse
    {
        if ($response->isClientError() || $response->isServerError()) {
            return $response;
        }

        if ($accepted !== null) {
            $response->header('Location', $accepted->statusUri);
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

    private function httpDate(string $day): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));

        if ($parsed === false || $parsed->format('Y-m-d') !== $day) {
            throw new ApiError(errorCode: CoreErrorCode::InternalError, status: 500, message: 'Operation could not be completed.');
        }

        return $parsed->format('D, d M Y H:i:s').' GMT';
    }
}
