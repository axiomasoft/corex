<?php

declare(strict_types=1);

namespace App\TransportProof;

use App\Scenario\Scenario;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

final class Wire
{
    public static function http(string $method, string $uri, array $body = [], array $options = []): array
    {
        $headers = [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.($options['token'] ?? 'transport-alpha'),
            ...($options['headers'] ?? []),
        ];

        if (isset($options['key'])) {
            $headers['HTTP_IDEMPOTENCY_KEY'] = $options['key'];
        }
        $content = $method === 'GET' ? null : json_encode((object) $body, JSON_THROW_ON_ERROR);
        $request = Request::create($uri, $method, [], [], [], $headers, $content);
        // Use a fresh installed Laravel application just as a separate web request would.
        $kernel = Scenario::boot()->make(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $raw = (string) $response->getContent();
        $route = $request->route();

        $transcript = [
            'method' => $method, 'uri' => $uri, 'request_body' => $body,
            'key' => $options['key'] ?? null, 'identity' => $request->attributes->get('installed_scope_evidence'),
            'if_none_match' => $request->headers->get('If-None-Match'), 'accept_language' => $request->headers->get('Accept-Language'),
            'status' => $response->getStatusCode(), 'media_type' => $response->headers->get('Content-Type'),
            'location' => $response->headers->get('Location'), 'etag' => $response->headers->get('ETag'),
            'cache' => $response->headers->get('Cache-Control'), 'size' => strlen($raw), 'sha256' => hash('sha256', $raw),
            'body' => json_decode($raw, true),
            'route' => $route?->getName(), 'action' => $route?->getActionName(), 'middleware' => $route?->gatherMiddleware(),
        ];
        $evidence = getenv('COREX_SCENARIO_BARRIERS');

        if (is_string($evidence)) {
            file_put_contents($evidence.'/wire-'.getmypid().'.ndjson', json_encode($transcript, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
        }

        return $transcript;
    }

    public static function rpc(string $method, array $params = [], array $options = [], int $id = 1): array
    {
        $request = ['jsonrpc' => '2.0', 'method' => $method, 'params' => (object) $params];

        if ($method !== 'notifications/initialized') {
            $request['id'] = $id;
        }

        return self::http('POST', '/mcp', $request, $options);
    }

    public static function call(string $tool, array $input, string $key, array $options = [], int $id = 2): array
    {
        return self::rpc('tools/call', ['name' => $tool, 'arguments' => (object) $input, 'operationKey' => $key], $options, $id);
    }
}
