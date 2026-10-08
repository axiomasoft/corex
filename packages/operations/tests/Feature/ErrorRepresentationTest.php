<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Errors\Internal\OperationErrorBoundary;
use CoreX\Operations\Errors\Internal\ProblemDetailsSerializer;
use CoreX\Operations\Errors\Internal\RetryAfterPolicy;
use CoreX\Operations\Tests\Support\SyntheticErrorCode;
use CoreX\Operations\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class ErrorRepresentationTest extends TestCase
{
    public function test_every_category_preserves_machine_fields_and_registry_identity(): void
    {
        $registry = SyntheticErrorCode::registry();
        $serializer = new ProblemDetailsSerializer($registry);

        foreach (ErrorCategory::cases() as $category) {
            $code = SyntheticErrorCode::fromCategory($category);
            $error = new ApiError(
                errorCode: $code,
                status: $registry->get($code)->defaultStatus,
                message: 'Safe '.$category->value.' message.',
                details: JsonDocuments::capture(['reason' => $category->value]),
                category: $category,
                retryable: $category === ErrorCategory::Conflict,
                path: '/attributes/color',
            );
            $problem = $serializer->error(error: $error, metadata: new InvocationMetadata('req-1', 'v1', '', 'GET', 'corr-1'))->getData(true);

            self::assertSame($error->status, $problem['status']);
            self::assertSame($code->value(), $problem['code']);
            self::assertSame($category->value, $problem['category']);
            self::assertSame('/attributes/color', $problem['path']);
            self::assertSame($category === ErrorCategory::Conflict, $problem['retryable']);
            self::assertSame(['reason' => $category->value], $problem['details']);
            self::assertSame('req-1', $problem['request_id']);
            self::assertSame('corr-1', $problem['correlation_id']);
            self::assertSame('v1', $problem['api_version']);
            self::assertSame($registry->get($code)->type, $problem['type']);
            self::assertSame($registry->get($code)->title, $problem['title']);
            self::assertSame($error->getMessage(), $problem['detail']);
            self::assertStringNotContainsString('Throwable', json_encode($problem, JSON_THROW_ON_ERROR));
        }
    }

    public function test_every_registered_problem_can_be_serialized(): void
    {
        $registry = $this->app->make(ProblemRegistry::class);
        $serializer = $this->app->make(ResponseSerializer::class);
        $metadata = new InvocationMetadata('req-1', 'v1', '', 'GET');

        self::assertNotEmpty($registry->all());
        foreach ($registry->all() as $definition) {
            $error = new ApiError(errorCode: $definition->code, status: $definition->defaultStatus, message: 'Occurrence detail.', category: $definition->category, retryable: $definition->retryable);
            $registry->assertOccurrence(descriptor: $error->errorDescriptor(), status: $error->status);
            $response = $serializer->error(error: $error, metadata: $metadata);
            $problem = $response->getData(true);
            self::assertSame($definition->type, $problem['type']);
            self::assertSame($definition->title, $problem['title']);
            self::assertSame('Occurrence detail.', $problem['detail']);
            self::assertSame($definition->defaultStatus, $response->getStatusCode());
            self::assertSame('application/problem+json', $response->headers->get('Content-Type'));
            foreach (['instance', 'path', 'message', 'error', 'data'] as $member) {
                self::assertArrayNotHasKey($member, $problem);
            }
        }
    }

    public function test_human_message_change_leaves_the_machine_fixture_unchanged(): void
    {
        $serializer = $this->app->make(ResponseSerializer::class);
        $metadata = new InvocationMetadata('req-1', 'v1', '', 'GET', 'corr-1');
        $first = $serializer->error(new ApiError(OperationsErrorCode::Conflict, 409, 'First safe message.', category: ErrorCategory::Conflict, retryable: true, path: '/a'), $metadata)->getData(true);
        $second = $serializer->error(new ApiError(OperationsErrorCode::Conflict, 409, 'Second safe message.', category: ErrorCategory::Conflict, retryable: true, path: '/a'), $metadata)->getData(true);

        self::assertNotSame($first['detail'], $second['detail']);
        unset($first['detail'], $second['detail']);
        self::assertSame($first, $second);
    }

    public static function accepts(): array
    {
        return [[''], ['application/json'], ['application/problem+json'], ['text/html'], ['*/*;q=0'], ['application/problem+json;q=0.9, application/json;q=0.1']];
    }

    #[DataProvider('accepts')]
    public function test_accept_always_receives_problem_json(string $accept): void
    {
        $this->app['router']->get('/represented/{version}', static function (): never {
            throw new ApiError(OperationsErrorCode::Forbidden, 403, 'Operation is not permitted.');
        })->middleware(OperationErrorBoundary::class);

        $response = $this->call('GET', '/represented/v1', server: ['HTTP_ACCEPT' => $accept, 'HTTP_X_REQUEST_ID' => 'req-1', 'HTTP_X_CORRELATION_ID' => 'corr-1']);
        $definition = $this->app->make(ProblemRegistry::class)->get(OperationsErrorCode::Forbidden);
        $response->assertForbidden()->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'operations.forbidden')->assertJsonPath('type', $definition->type)
            ->assertJsonPath('title', $definition->title)->assertJsonPath('detail', 'Operation is not permitted.')
            ->assertJsonPath('request_id', 'req-1')->assertJsonPath('correlation_id', 'corr-1');
        self::assertNull($response->headers->get('Vary'));
        self::assertNull($response->headers->get('Retry-After'));
        foreach (['instance', 'path', 'message', 'error', 'data'] as $member) {
            self::assertArrayNotHasKey($member, $response->json());
        }
    }

    public function test_retry_after_requires_a_named_bounded_policy(): void
    {
        $serializer = $this->app->make(ResponseSerializer::class);
        $metadata = new InvocationMetadata('req-1', 'v1', '', 'GET');
        $retryable = $serializer->error(new ApiError(OperationsErrorCode::Conflict, 409, 'Later.', category: ErrorCategory::Conflict, retryable: true), $metadata);
        $named = $serializer->error(new ApiError(OperationsErrorCode::Conflict, 409, 'Later.', category: ErrorCategory::Conflict, retryable: true, retryAfterPolicy: RetryAfterPolicy::RATE_LIMIT), $metadata);
        $unknown = $serializer->error(new ApiError(OperationsErrorCode::Conflict, 409, 'Later.', category: ErrorCategory::Conflict, retryable: true, retryAfterPolicy: 'custom'), $metadata);

        self::assertNull($retryable->headers->get('Retry-After'));
        self::assertSame('60', $named->headers->get('Retry-After'));
        self::assertNull($unknown->headers->get('Retry-After'));
    }

    public function test_unknown_exception_stays_a_safe_internal_error(): void
    {
        $this->app['router']->get('/exploded/{version}', static function (): never {
            throw new RuntimeException('SQLSTATE secret stack');
        })->middleware(OperationErrorBoundary::class);

        $response = $this->call('GET', '/exploded/v1', server: ['HTTP_ACCEPT' => 'application/problem+json', 'HTTP_X_REQUEST_ID' => 'req-1']);
        $response->assertStatus(500)->assertJsonPath('code', 'core.internal_error')->assertJsonPath('detail', 'Operation could not be completed.');
        self::assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
        self::assertStringNotContainsString('secret', (string) $response->getContent());
    }
}
