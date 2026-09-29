<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Operations\Invocation\InvocationMetadata;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class InvocationMetadataTest extends TestCase
{
    public function test_four_argument_construction_falls_back_to_request_id(): void
    {
        $metadata = new InvocationMetadata('req-1', 'v1', 'notes.read', 'GET');

        $this->assertSame('req-1', $metadata->requestId);
        $this->assertSame('req-1', $metadata->correlationId);
    }

    public function test_valid_correlation_id_is_preserved(): void
    {
        $metadata = InvocationMetadata::fromRequest(
            request: Request::create(
                uri: '/v1/notes',
                method: 'GET',
                server: [
                    'HTTP_X_REQUEST_ID' => 'req-abc',
                    'HTTP_X_CORRELATION_ID' => 'corr-xyz',
                ],
            ),
            version: 'v1',
        );

        $this->assertSame('req-abc', $metadata->requestId);
        $this->assertSame('corr-xyz', $metadata->correlationId);
    }

    public function test_missing_correlation_id_falls_back_to_request_id(): void
    {
        $metadata = InvocationMetadata::fromRequest(
            request: Request::create(
                uri: '/v1/notes',
                method: 'GET',
                server: ['HTTP_X_REQUEST_ID' => 'req-abc'],
            ),
            version: 'v1',
        );

        $this->assertSame('req-abc', $metadata->correlationId);
    }

    public function test_invalid_correlation_and_request_ids_are_rejected_identically(): void
    {
        $metadata = InvocationMetadata::fromRequest(
            request: Request::create(
                uri: '/v1/notes',
                method: 'GET',
                server: [
                    'HTTP_X_REQUEST_ID' => str_repeat('x', 129),
                    'HTTP_X_CORRELATION_ID' => 'corr id',
                ],
            ),
            version: 'v1',
        );

        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/D', $metadata->requestId);
        $this->assertSame($metadata->requestId, $metadata->correlationId);
    }

    public function test_invalid_correlation_falls_back_to_valid_request_id(): void
    {
        $metadata = InvocationMetadata::fromRequest(
            request: Request::create(
                uri: '/v1/notes',
                method: 'GET',
                server: [
                    'HTTP_X_REQUEST_ID' => 'req-abc',
                    'HTTP_X_CORRELATION_ID' => str_repeat('y', 129),
                ],
            ),
            version: 'v1',
        );

        $this->assertSame('req-abc', $metadata->requestId);
        $this->assertSame('req-abc', $metadata->correlationId);
    }
}
