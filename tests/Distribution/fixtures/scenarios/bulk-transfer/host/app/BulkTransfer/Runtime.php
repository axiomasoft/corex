<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use App\Scenario\ScenarioQueue;
use Closure;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkScopeRunner;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class Runtime
{
    public const string BASE = '/api/v1/bulk-operations';

    /** @return array{status: int, body: array, location: ?string, cache: ?string} */
    public static function http(string $method, string $uri, array $data = [], ?string $key = null): array
    {
        $headers = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];

        if ($key !== null) {
            $headers['HTTP_IDEMPOTENCY_KEY'] = $key;
        }
        $request = Request::create($uri, $method, [], [], [], $headers, json_encode($data, JSON_THROW_ON_ERROR));
        $kernel = app(Kernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return self::decode($response);
    }

    public static function submit(string $kind, string $key, string $source = 'equipment'): array
    {
        return self::http('POST', self::BASE, ['kind' => $kind, 'source' => $source], $key);
    }

    public static function scoped(Closure $work, ?string $workspace = Scenario::W1): mixed
    {
        return ScenarioQueue::inScope(Scenario::BOXED, $workspace, Scenario::USER_A, static function () use ($work): mixed {
            $scope = app(DataScopeFactory::class)->forCurrentPrincipal(app(TenantContextResolver::class)->current());

            return app(WorkScopeRunner::class)->run($scope, static fn (): mixed => $work($scope));
        });
    }

    public static function work(int $seconds): int
    {
        return ScenarioQueue::inScope(Scenario::BOXED, Scenario::W1, Scenario::USER_A, static function () use ($seconds): int {
            app(ScenarioClock::class)->advance($seconds);
            CrashRecovery::start();

            return Artisan::call('corex:operations:bulk-work', ['--worker' => 'api-bulk']);
        });
    }

    public static function tenant(): Connection
    {
        return app('db')->connection('pgsql');
    }

    public static function bulk(string $kind): object
    {
        return self::tenant()->table('sys_api_bulk_operations')->whereRaw("input_ref->>'kind' = ?", [$kind])
            ->orderByDesc('id')->firstOrFail();
    }

    public static function id(string $kind): string
    {
        return self::bulk($kind)->id;
    }

    public static function transfer(string $operation): string
    {
        // The installed equipment adapter specifies separate deterministic names for its job and manifest.
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'corex:equipment:transfer:'.$operation)->toString();
    }

    public static function snapshot(object $row): array
    {
        return ['status' => $row->status, 'revision' => (int) $row->revision, 'attempt' => (int) $row->attempt,
            'checkpoint' => json_decode($row->checkpoint, true, flags: JSON_THROW_ON_ERROR),
            'error' => $row->error === null ? null : json_decode($row->error, true, flags: JSON_THROW_ON_ERROR)];
    }

    public static function budget(): array
    {
        return Scenario::connection('budget')->table('sys_api_budget_reservations')->orderBy('id')->get()
            ->map(static fn (object $row): array => ['id' => $row->id, 'state' => $row->state, 'charged' => (int) $row->charged])->all();
    }

    private static function decode(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('HTTP '.$response->getStatusCode().' returned a non-JSON response.');
        }

        return ['status' => $response->getStatusCode(), 'body' => $decoded, 'location' => $response->headers->get('Location'),
            'cache' => $response->headers->get('Cache-Control')];
    }
}
