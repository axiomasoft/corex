<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Operations\Errors\ApiError;
use CoreX\Operations\Errors\Internal\EnvelopeSerializer;
use CoreX\Operations\Errors\Internal\ErrorWire;
use CoreX\Operations\Errors\Internal\NegotiatingResponseSerializer;
use CoreX\Operations\Errors\Internal\RetryAfterPolicy;
use CoreX\Operations\Errors\OperationErrorBoundary;
use CoreX\Operations\Invocation\InvocationMetadata;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Tests\TestCase;
use RuntimeException;

final class ErrorRepresentationTest extends TestCase
{
    public function test_every_category_keeps_the_same_machine_fields_in_both_representations(): void
    {
        $serializer = $this->app->make(NegotiatingResponseSerializer::class);
        $options = $this->app->make(ApiOptions::class);

        foreach (ErrorCategory::cases() as $category) {
            $error = new ApiError(
                errorCode: 'synthetic_'.$category->value,
                status: 409,
                message: 'Safe '.$category->value.' message.',
                details: ['reason' => $category->value],
                category: $category,
                retryable: $category === ErrorCategory::Conflict,
                path: '/attributes/color',
            );
            $metadata = new InvocationMetadata('req-1', 'v1', '', 'GET', 'corr-1');
            $envelope = $serializer->error($error, $metadata)->getData(true);
            $problem = $serializer->error($error, new InvocationMetadata('req-1', 'v1', '', 'GET', 'corr-1', 'application/problem+json'))->getData(true);

            $this->assertSame($error->status, $problem['status']);
            $this->assertSame($envelope['error']['code'], $problem['code']);
            $this->assertSame($category->value, $envelope['error']['category']);
            $this->assertSame($envelope['error']['category'], $problem['category']);
            $this->assertSame('/attributes/color', $envelope['error']['path']);
            $this->assertSame($envelope['error']['path'], $problem['path']);
            $this->assertSame($envelope['error']['retryable'], $problem['retryable']);
            $this->assertSame(['reason' => $category->value], $envelope['error']['details']);
            $this->assertSame($envelope['error']['details'], $problem['details']);
            $this->assertSame('req-1', $envelope['meta']['request_id']);
            $this->assertSame($envelope['meta']['request_id'], $problem['request_id']);
            $this->assertSame('corr-1', $envelope['meta']['correlation_id']);
            $this->assertSame($envelope['meta']['correlation_id'], $problem['correlation_id']);
            $this->assertSame('v1', $envelope['meta']['api_version']);
            $this->assertSame($envelope['meta']['api_version'], $problem['api_version']);
            $this->assertSame(ErrorWire::type($options->problemTypeBase, $error->errorCode), $problem['type']);
            $this->assertSame(ErrorWire::instance($options->problemTypeBase, 'req-1'), $problem['instance']);
            $this->assertStringNotContainsString('Throwable', json_encode($problem, JSON_THROW_ON_ERROR));
        }
    }

    public function test_human_message_change_leaves_the_machine_fixture_unchanged(): void
    {
        $serializer = new EnvelopeSerializer;
        $metadata = new InvocationMetadata('req-1', 'v1', '', 'GET', 'corr-1');
        $first = $serializer->error(new ApiError('conflict', 409, 'First safe message.', category: ErrorCategory::Conflict, retryable: true, path: '/a'), $metadata)->getData(true);
        $second = $serializer->error(new ApiError('conflict', 409, 'Second safe message.', category: ErrorCategory::Conflict, retryable: true, path: '/a'), $metadata)->getData(true);

        $this->assertNotSame($first['error']['message'], $second['error']['message']);
        unset($first['error']['message'], $second['error']['message']);
        $this->assertSame($first, $second);
    }

    public function test_accept_q_values_wildcards_and_unsupported_types(): void
    {
        $this->app['router']->get('/represented/{version}', static function (): never {
            throw new ApiError('forbidden', 403, 'Operation is not permitted.', retryable: true);
        })->middleware(OperationErrorBoundary::class);

        $problem = $this->call('GET', '/represented/v1', server: ['HTTP_ACCEPT' => 'application/problem+json;q=0.9, application/json;q=0.1', 'HTTP_X_REQUEST_ID' => 'req-1', 'HTTP_X_CORRELATION_ID' => 'corr-1']);
        $problem->assertForbidden();
        $this->assertStringContainsString('application/problem+json', (string) $problem->headers->get('Content-Type'));
        $this->assertSame('forbidden', $problem->json('code'));
        $this->assertSame('Accept', $problem->headers->get('Vary'));
        $this->assertNull($problem->headers->get('Retry-After'));

        $wildcard = $this->call('GET', '/represented/v1', server: ['HTTP_ACCEPT' => '*/*', 'HTTP_X_REQUEST_ID' => 'req-1']);
        $wildcard->assertForbidden();
        $this->assertSame('forbidden', $wildcard->json('error.code'));
        $this->assertStringContainsString('application/json', (string) $wildcard->headers->get('Content-Type'));

        $unsupported = $this->call('GET', '/represented/v1', server: ['HTTP_ACCEPT' => 'text/html', 'HTTP_X_REQUEST_ID' => 'req-1']);
        $unsupported->assertStatus(406);
        $this->assertSame('request_rejected', $unsupported->json('error.code'));
        $this->assertStringNotContainsString('requested representation', (string) $unsupported->getContent());
        $this->assertStringContainsString('application/json', (string) $unsupported->headers->get('Content-Type'));
    }

    public function test_retry_after_requires_a_named_bounded_policy(): void
    {
        $serializer = new EnvelopeSerializer;
        $metadata = new InvocationMetadata('req-1', 'v1', '', 'GET');
        $retryable = $serializer->error(new ApiError('conflict', 409, 'Later.', category: ErrorCategory::Conflict, retryable: true), $metadata);
        $named = $serializer->error(new ApiError('conflict', 409, 'Later.', category: ErrorCategory::Conflict, retryable: true, retryAfterPolicy: RetryAfterPolicy::RATE_LIMIT), $metadata);
        $unknown = $serializer->error(new ApiError('conflict', 409, 'Later.', category: ErrorCategory::Conflict, retryable: true, retryAfterPolicy: 'custom'), $metadata);

        $this->assertNull($retryable->headers->get('Retry-After'));
        $this->assertSame('60', $named->headers->get('Retry-After'));
        $this->assertNull($unknown->headers->get('Retry-After'));
    }

    public function test_unknown_exception_stays_a_safe_internal_error(): void
    {
        $this->app['router']->get('/exploded/{version}', static function (): never {
            throw new RuntimeException('SQLSTATE secret stack');
        })->middleware(OperationErrorBoundary::class);

        $response = $this->call('GET', '/exploded/v1', server: ['HTTP_ACCEPT' => 'application/problem+json', 'HTTP_X_REQUEST_ID' => 'req-1']);

        $response->assertStatus(500);
        $this->assertSame('internal_error', $response->json('code'));
        $this->assertSame('Operation could not be completed.', $response->json('detail'));
        $this->assertStringNotContainsString('SQLSTATE', (string) $response->getContent());
        $this->assertStringNotContainsString('secret', (string) $response->getContent());
    }
}
