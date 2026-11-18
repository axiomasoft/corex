<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use Illuminate\Http\Request;
use JsonException;
use Override;
use Packman\Attributes\Internal;

/** @internal */
#[Internal]
final class JsonObjectEnvelopeReader implements InputEnvelopeReader
{
    #[Override]
    public function read(Request $request): array
    {
        if ($request->query->count() !== 0) {
            throw new ApiError(errorCode: OperationsErrorCode::InputEnvelopeInvalid, status: 422, message: 'Query parameters are not accepted with a request body.');
        }

        if (! $request->isJson()) {
            throw new ApiError(errorCode: OperationsErrorCode::UnsupportedMediaType, status: 415, message: 'A JSON request body is required.');
        }

        try {
            $body = $request->getContent();
            $input = json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiError(errorCode: OperationsErrorCode::InputEnvelopeInvalid, status: 422, message: 'Malformed JSON body.');
        }

        if (! is_array($input) || ! str_starts_with(ltrim($body), '{')) {
            throw new ApiError(errorCode: OperationsErrorCode::InputEnvelopeInvalid, status: 422, message: 'A JSON object body is required.');
        }

        return $input;
    }
}
