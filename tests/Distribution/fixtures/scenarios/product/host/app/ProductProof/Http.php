<?php

declare(strict_types=1);

namespace App\ProductProof;

use App\Scenario\Scenario;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

final class Http
{
    public static function send(string $method, string $uri, array $body = [], ?string $key = null, string $actor = 'fixture-crm-a'): array
    {
        $headers = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$actor,
            'CONTENT_TYPE' => 'application/json',
        ];

        if ($key !== null) {
            $headers['HTTP_IDEMPOTENCY_KEY'] = $key;
        }
        $request = Request::create($uri, $method, [], [], [], $headers, $method === 'GET' ? null : json_encode((object) $body, JSON_THROW_ON_ERROR));
        // Use a fresh installed Laravel application just as a separate web request would.
        $kernel = Scenario::boot()->make(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
        $content = (string) $response->getContent();

        $transcript = [
            'method' => $method, 'uri' => $uri, 'actor' => $actor,
            'status' => $response->getStatusCode(), 'location' => $response->headers->get('Location'),
            'media_type' => $response->headers->get('Content-Type'), 'body' => json_decode($content, true),
            'route' => $request->route()?->getName(),
        ];
        $evidence = getenv('COREX_SCENARIO_BARRIERS');

        if (is_string($evidence)) {
            file_put_contents($evidence.'/wire-'.getmypid().'.ndjson', json_encode($transcript, JSON_THROW_ON_ERROR)."\n", FILE_APPEND | LOCK_EX);
        }

        return $transcript;
    }
}
