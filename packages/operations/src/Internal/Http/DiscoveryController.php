<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal\Http;

use CoreX\Operations\Invocation\Contracts\EffectiveCapabilities as CapabilityResolver;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\OpenApi\Contracts\OpenApiDocuments as OpenApiDocumentGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Packman\Attributes\Internal;

/** @internal HTTP projection of the compiled API profile. */
#[Internal]
final readonly class DiscoveryController
{
    public function __construct(
        private ApiOptions $options,
        private CapabilityResolver $capabilities,
        private OpenApiDocumentGenerator $openApi,
    ) {}

    public function root(): JsonResponse
    {
        $version = $this->options->defaultMajor;
        $base = $this->options->publicBaseUri();

        return new JsonResponse([
            'build' => $this->options->build,
            'default_major' => $version,
            'links' => [
                'capabilities' => $base.'/capabilities',
                'openapi' => $base.'/openapi.json',
            ],
            'product' => $this->options->product,
            'versions' => array_map(
                static fn ($row) => $row->toArray(),
                $this->options->versions,
            ),
        ]);
    }

    public function capabilities(Request $request): JsonResponse
    {
        return new JsonResponse([
            'capabilities' => $this->capabilities->effective($request),
            'profile' => $this->capabilities->fingerprint(),
        ]);
    }

    public function openapi(): JsonResponse
    {
        return new JsonResponse($this->openApi->document(version: 'v2', serverUrl: $this->options->publicBaseUri()));
    }
}
