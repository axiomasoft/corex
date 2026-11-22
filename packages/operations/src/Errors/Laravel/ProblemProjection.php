<?php

declare(strict_types=1);

namespace CoreX\Operations\Errors\Laravel;

use CoreX\Operations\Errors\Exceptions\ApiError;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Contracts\ProblemRegistry;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final readonly class ProblemProjection
{
    public function __construct(private ProblemRegistry $problems) {}

    /** @return array<string, mixed> */
    public function document(ApiError $error): array
    {
        $descriptor = $error->errorDescriptor();
        $this->problems->assertOccurrence(descriptor: $descriptor, status: $error->status);
        $definition = $this->problems->fromWire($descriptor->code);
        $body = [
            'type' => $definition->type,
            'title' => $definition->title,
            'status' => $error->status,
            'detail' => $error->getMessage(),
            'code' => $descriptor->code,
            'category' => $descriptor->category->value,
            'retryable' => $descriptor->retryable,
            'details' => (object) $descriptor->details,
        ];

        if ($descriptor->path !== null) {
            $body['path'] = $descriptor->path;
        }

        return $body;
    }
}
