<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Operations\Errors\ApiError;
use CoreX\Operations\Errors\Internal\EnvelopeSerializer;
use CoreX\Operations\Errors\Internal\ErrorMapper;
use CoreX\Operations\Errors\OperationErrorBoundary;
use CoreX\Operations\Invocation\InvocationMetadata;
use CoreX\Operations\Tests\Support\ErrorBoundaryCountingHandler;
use CoreX\Operations\Tests\Support\SyntheticPackageFailure;
use CoreX\Operations\Tests\TestCase;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class OperationErrorBoundaryTest extends TestCase
{
    public function test_old_wire_snapshot_has_no_new_error_fields(): void
    {
        $response = (new EnvelopeSerializer)->error(
            error: new ApiError('forbidden', 403, 'Operation is not permitted.'),
            metadata: new InvocationMetadata('req-1', 'v1', '', 'GET', 'corr-1'),
        );
        $payload = $response->getData(true);
        $json = $response->getContent();

        $this->assertSame([
            'data' => null,
            'error' => [
                'code' => 'forbidden',
                'message' => 'Operation is not permitted.',
                'category' => 'authorization',
                'path' => null,
                'retryable' => false,
                'details' => [],
            ],
            'meta' => [
                'request_id' => 'req-1',
                'correlation_id' => 'corr-1',
                'api_version' => 'v1',
            ],
        ], $payload);
        $this->assertSame('req-1', $response->headers->get('X-Request-ID'));
        $this->assertSame('Accept', $response->headers->get('Vary'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertIsString($json);
        $this->assertArrayNotHasKey('X-Correlation-ID', $response->headers->all());
    }

    public function test_four_argument_api_error_still_describes_itself(): void
    {
        $error = new ApiError('forbidden', 403, 'Operation is not permitted.');
        $descriptor = $error->errorDescriptor();

        $this->assertSame('forbidden', $descriptor->code);
        $this->assertSame(ErrorCategory::Authorization, $descriptor->category);
        $this->assertFalse($descriptor->retryable);
        $this->assertNull($descriptor->path);
        $this->assertSame([], $descriptor->details);
        $this->assertSame(403, $error->status);
    }

    public function test_explicit_api_error_keeps_its_status(): void
    {
        $error = new ApiError(
            errorCode: 'hidden',
            status: 404,
            message: 'Resource not found.',
            category: ErrorCategory::Authorization,
        );

        $mapped = (new ErrorMapper)->map($error);

        $this->assertSame($error, $mapped);
        $this->assertSame(404, $mapped->status);
        $this->assertSame(ErrorCategory::Authorization, $mapped->errorDescriptor()->category);
    }

    public function test_described_error_maps_every_category_to_its_default_status(): void
    {
        $expected = [
            ErrorCategory::Authentication->value => 401,
            ErrorCategory::Authorization->value => 403,
            ErrorCategory::Validation->value => 422,
            ErrorCategory::Conflict->value => 409,
            ErrorCategory::NotFound->value => 404,
            ErrorCategory::RateLimit->value => 429,
            ErrorCategory::Unsupported->value => 422,
            ErrorCategory::Unavailable->value => 503,
            ErrorCategory::Internal->value => 500,
        ];

        $this->assertCount(9, ErrorCategory::cases());

        foreach (ErrorCategory::cases() as $category) {
            $mapped = (new ErrorMapper)->map(new SyntheticPackageFailure(new ErrorDescriptor(
                code: 'synthetic_'.$category->value,
                category: $category,
                retryable: $category === ErrorCategory::Unavailable,
                path: '/field',
                details: ['reason' => $category->value],
            )));

            $this->assertSame($expected[$category->value], $mapped->status);
            $this->assertSame('synthetic_'.$category->value, $mapped->errorCode);
            $this->assertSame($category, $mapped->category);
            $this->assertSame($category === ErrorCategory::Unavailable, $mapped->retryable);
            $this->assertSame('/field', $mapped->path);
            $this->assertSame(['reason' => $category->value], $mapped->details);
            $this->assertStringNotContainsString('secret', $mapped->getMessage());
        }
    }

    public function test_unknown_exception_reports_once_and_leaks_nothing(): void
    {
        $reported = [];
        $this->app->instance(ExceptionHandler::class, new ErrorBoundaryCountingHandler($reported));

        $mapped = (new ErrorMapper)->map(new RuntimeException('private SQLSTATE secret'));
        $this->assertCount(1, $reported);
        $this->assertSame('internal_error', $mapped->errorCode);
        $this->assertSame([], $mapped->details);
        $this->assertStringNotContainsString('secret', $mapped->getMessage());
        $this->assertSame($mapped->errorCode, (new ErrorMapper)->map($mapped)->errorCode);
        $this->assertCount(1, $reported);

        $this->app['router']->get('/boom/{version}', static function (): never {
            throw new RuntimeException('private SQLSTATE secret');
        })->middleware(OperationErrorBoundary::class);

        $response = $this->getJson('/boom/v1', ['X-Request-ID' => 'req-boom']);

        $response->assertStatus(500)
            ->assertExactJson([
                'data' => null,
                'error' => [
                    'code' => 'internal_error',
                    'message' => 'Operation could not be completed.',
                    'category' => 'internal',
                    'path' => null,
                    'retryable' => false,
                    'details' => [],
                ],
                'meta' => [
                    'request_id' => 'req-boom',
                    'correlation_id' => 'req-boom',
                    'api_version' => 'v1',
                ],
            ])
            ->assertHeader('X-Request-ID', 'req-boom');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('secret', (string) $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
        $this->assertSame(ErrorCategory::Internal, (new ErrorMapper)->map(new RuntimeException('x'), reportUnexpected: false)->category);
    }

    public function test_framework_fallbacks_stay_safe_and_gain_a_category(): void
    {
        $mapper = new ErrorMapper;
        $validation = $mapper->map(ValidationException::withMessages(['title' => ['Required.']]));
        $http = $mapper->map(new HttpException(409, 'private secret'));

        $this->assertSame(422, $validation->status);
        $this->assertSame(ErrorCategory::Validation, $validation->category);
        $this->assertSame(['title' => ['Required.']], $validation->details);
        $this->assertSame(409, $http->status);
        $this->assertSame('conflict', $http->errorCode);
        $this->assertSame(ErrorCategory::Conflict, $http->category);
        $this->assertStringNotContainsString('secret', $http->getMessage());
    }

    public function test_boundary_preserves_valid_correlation_internally_without_wire_change(): void
    {
        $this->app['router']->get('/denied/{version}', static function (): never {
            throw new ApiError('forbidden', 403, 'Operation is not permitted.');
        })->middleware(OperationErrorBoundary::class);

        $response = $this->getJson('/denied/v2', [
            'X-Request-ID' => 'req-123',
            'X-Correlation-ID' => 'corr-456',
        ]);
        $metadata = InvocationMetadata::fromRequest(
            request: Request::create('/denied/v2', 'GET', server: [
                'HTTP_X_REQUEST_ID' => 'req-123',
                'HTTP_X_CORRELATION_ID' => 'corr-456',
            ]),
            version: 'v2',
        );

        $response->assertForbidden()
            ->assertExactJson([
                'data' => null,
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'Operation is not permitted.',
                    'category' => 'authorization',
                    'path' => null,
                    'retryable' => false,
                    'details' => [],
                ],
                'meta' => [
                    'request_id' => 'req-123',
                    'correlation_id' => 'corr-456',
                    'api_version' => 'v2',
                ],
            ]);
        $this->assertSame('corr-456', $metadata->correlationId);
        $this->assertNull($response->headers->get('X-Correlation-ID'));
        $this->assertSame('Accept', $response->headers->get('Vary'));
    }
}
