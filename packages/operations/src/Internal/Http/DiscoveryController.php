<?php

declare(strict_types=1);

namespace CoreX\Operations\Internal\Http;

use CoreX\Operations\Invocation\Contracts\EffectiveCapabilities as CapabilityResolver;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\OpenApi\Contracts\OpenApiDocuments as OpenApiDocumentGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** @internal HTTP projection of the compiled API profile. */
final class DiscoveryController
{
    public function __construct(
        private readonly ApiOptions $options,
        private readonly CapabilityResolver $capabilities,
        private readonly OpenApiDocumentGenerator $openApi,
    ) {}

    public function root(): JsonResponse
    {
        $version = $this->options->defaultMajor;

        return new JsonResponse([
            'build' => $this->options->build,
            'default_major' => $version,
            'links' => [
                'capabilities' => '/'.$this->options->routePrefix.'/'.$version.'/capabilities',
                'openapi' => '/'.$this->options->routePrefix.'/'.$version.'/openapi.json',
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

    public function openapi(Request $request): JsonResponse
    {
        $version = $request->route('version');

        return new JsonResponse($this->openApi->document(is_string($version) ? $version : $this->options->defaultMajor));
    }
}
