<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\ApiError;
use CoreX\Api\Contracts\ResponseSerializer;
use CoreX\Api\InvocationMetadata;
use Illuminate\Http\JsonResponse;
use Override;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** @internal HTTP projection. The invoker stays transport-neutral. */
final class NegotiatingResponseSerializer implements ResponseSerializer
{
    public function __construct(
        private readonly EnvelopeSerializer $envelope,
        private readonly ProblemDetailsSerializer $problems,
        private readonly ErrorMapper $errors,
    ) {}

    #[Override]
    public function success(array $data, int $status, InvocationMetadata $metadata): JsonResponse
    {
        return $this->envelope->success($data, $status, $metadata);
    }

    #[Override]
    public function error(ApiError $error, InvocationMetadata $metadata): JsonResponse
    {
        $selected = AcceptNegotiation::select($metadata->accept);

        if ($selected === AcceptNegotiation::PROBLEM) {
            return $this->problems->error($error, $metadata);
        }

        if ($selected === AcceptNegotiation::JSON) {
            return $this->envelope->error($error, $metadata);
        }

        return $this->envelope->error(
            $this->errors->map(new HttpException(406, 'requested representation')),
            $metadata,
        );
    }
}
