<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Contracts\DescribesError;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Errors\Internal\ErrorMapper;
use CoreX\Operations\Errors\Internal\OperationErrorBoundary;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Laravel\InvocationMetadataFactory;
use CoreX\Operations\Tests\Support\ErrorBoundaryCountingHandler;
use CoreX\Operations\Tests\Support\SyntheticErrorCode;
use CoreX\Operations\Tests\Support\SyntheticPackageFailure;
use CoreX\Operations\Tests\TestCase;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class OperationErrorBoundaryTest extends TestCase
{
    public function test_problem_wire_snapshot_has_registry_identity_and_no_envelope(): void
    {
        $response = $this->app->make(ResponseSerializer::class)->error(
            error: new ApiError(OperationsErrorCode::Forbidden, 403, 'Operation is not permitted.'),
            metadata: new InvocationMetadata('req-1', 'v1', '', 'GET', 'corr-1'),
        );
        $payload = $response->getData(true);
        $json = $response->getContent();

        $this->assertSame([
            'type' => 'urn:corex:problem:operations.forbidden',
            'title' => 'Forbidden.',
            'status' => 403,
            'detail' => 'Operation is not permitted.',
            'code' => 'operations.forbidden',
            'category' => 'authorization',
            'retryable' => false,
            'details' => [],
            'request_id' => 'req-1',
            'correlation_id' => 'corr-1',
            'api_version' => 'v1',
        ], $payload);
        $this->assertSame('req-1', $response->headers->get('X-Request-ID'));
        $this->assertNull($response->headers->get('Vary'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertIsString($json);
        $this->assertArrayNotHasKey('X-Correlation-ID', $response->headers->all());
    }

    public function test_four_argument_api_error_still_describes_itself(): void
    {
        $error = new ApiError(OperationsErrorCode::Forbidden, 403, 'Operation is not permitted.');
        $descriptor = $error->errorDescriptor();

        $this->assertSame('operations.forbidden', $descriptor->code);
        $this->assertSame(ErrorCategory::Authorization, $descriptor->category);
        $this->assertFalse($descriptor->retryable);
        $this->assertNull($descriptor->path);
        $this->assertSame([], $descriptor->details);
        $this->assertSame(403, $error->status);
    }

    public function test_explicit_api_error_keeps_its_status(): void
    {
        $error = new ApiError(
            errorCode: OperationsErrorCode::Forbidden,
            status: 404,
            message: 'Resource not found.',
            category: ErrorCategory::Authorization,
        );

        $mapped = (app(ErrorMapper::class))->map($error);

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
        $this->app->instance(ProblemRegistry::class, SyntheticErrorCode::registry());

        foreach (ErrorCategory::cases() as $category) {
            $mapped = (app(ErrorMapper::class))->map(new SyntheticPackageFailure(new ErrorDescriptor(
                code: SyntheticErrorCode::fromCategory($category),
                category: $category,
                retryable: $category === ErrorCategory::Unavailable,
                path: '/field',
                details: ['reason' => $category->value],
            )));

            $this->assertSame($expected[$category->value], $mapped->status);
            $this->assertSame('synthetic.'.$category->value, $mapped->errorCode);
            $this->assertSame($category, $mapped->category);
            $this->assertSame($category === ErrorCategory::Unavailable, $mapped->retryable);
            $this->assertSame('/field', $mapped->path);
            $this->assertSame(['reason' => $category->value], JsonDocuments::map($mapped->details));
            $this->assertStringNotContainsString('secret', $mapped->getMessage());
        }
    }

    public function test_unknown_exception_reports_once_and_leaks_nothing(): void
    {
        $reported = [];
        $this->app->instance(ExceptionHandler::class, new ErrorBoundaryCountingHandler($reported));

        $mapped = (app(ErrorMapper::class))->map(new RuntimeException('private SQLSTATE secret'));
        $this->assertCount(1, $reported);
        $this->assertSame('core.internal_error', $mapped->errorCode);
        $this->assertSame([], JsonDocuments::map($mapped->details));
        $this->assertStringNotContainsString('secret', $mapped->getMessage());
        $this->assertSame($mapped->errorCode, (app(ErrorMapper::class))->map($mapped)->errorCode);
        $this->assertCount(1, $reported);

        $this->app['router']->get('/boom/{version}', static function (): never {
            throw new RuntimeException('private SQLSTATE secret');
        })->middleware(OperationErrorBoundary::class);

        $response = $this->getJson('/boom/v1', ['X-Request-ID' => 'req-boom']);

        $response->assertStatus(500)
            ->assertExactJson([
                'type' => 'urn:corex:problem:core.internal_error',
                'title' => 'Internal error.',
                'status' => 500,
                'detail' => 'Operation could not be completed.',
                'code' => 'core.internal_error',
                'category' => 'internal',
                'retryable' => false,
                'details' => [],
                'request_id' => 'req-boom',
                'correlation_id' => 'req-boom',
                'api_version' => 'v1',
            ])
            ->assertHeader('X-Request-ID', 'req-boom');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('secret', (string) $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
        $this->assertSame(ErrorCategory::Internal, (app(ErrorMapper::class))->map(new RuntimeException('x'), reportUnexpected: false)->category);
    }

    public function test_a_broken_descriptor_producer_maps_to_the_safe_internal_error(): void
    {
        $error = new class extends RuntimeException implements DescribesError
        {
            public function errorDescriptor(): ErrorDescriptor
            {
                throw new RuntimeException('private descriptor secret');
            }
        };

        $mapped = (app(ErrorMapper::class))->map($error, reportUnexpected: false);
        $this->assertSame('core.internal_error', $mapped->errorCode);
        $this->assertSame(500, $mapped->status);
        $this->assertStringNotContainsString('secret', $mapped->getMessage());
    }

    public function test_framework_fallbacks_stay_safe_and_gain_a_category(): void
    {
        $mapper = app(ErrorMapper::class);
        $validation = $mapper->map(ValidationException::withMessages(['title' => ['Required.']]));
        $http = $mapper->map(new HttpException(409, 'private secret'));

        $this->assertSame(422, $validation->status);
        $this->assertSame(ErrorCategory::Validation, $validation->category);
        $this->assertSame(['title' => ['Required.']], JsonDocuments::map($validation->details));
        $this->assertSame(409, $http->status);
        $this->assertSame('operations.conflict', $http->errorCode);
        $this->assertSame(ErrorCategory::Conflict, $http->category);
        $this->assertStringNotContainsString('secret', $http->getMessage());
    }

    public function test_boundary_preserves_valid_correlation_internally_without_wire_change(): void
    {
        $this->app['router']->get('/denied/{version}', static function (): never {
            throw new ApiError(OperationsErrorCode::Forbidden, 403, 'Operation is not permitted.');
        })->middleware(OperationErrorBoundary::class);

        $response = $this->getJson('/denied/v2', [
            'X-Request-ID' => 'req-123',
            'X-Correlation-ID' => 'corr-456',
        ]);
        $metadata = InvocationMetadataFactory::fromRequest(
            request: Request::create('/denied/v2', 'GET', server: [
                'HTTP_X_REQUEST_ID' => 'req-123',
                'HTTP_X_CORRELATION_ID' => 'corr-456',
            ]),
            version: 'v2',
        );

        $response->assertForbidden()
            ->assertExactJson([
                'type' => 'urn:corex:problem:operations.forbidden',
                'title' => 'Forbidden.',
                'status' => 403,
                'detail' => 'Operation is not permitted.',
                'code' => 'operations.forbidden',
                'category' => 'authorization',
                'retryable' => false,
                'details' => [],
                'request_id' => 'req-123',
                'correlation_id' => 'corr-456',
                'api_version' => 'v2',
            ]);
        $this->assertSame('corr-456', $metadata->correlationId);
        $this->assertNull($response->headers->get('X-Correlation-ID'));
        $this->assertNull($response->headers->get('Vary'));
    }
}
