<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Tests\TestCase;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Errors\Enums\ErrorCategory;
use TypeError;

final class StoredOperationOutcomeTest extends TestCase
{
    public function test_registered_error_roundtrip_preserves_identity_and_descriptor(): void
    {
        $registry = $this->app->make(ProblemRegistry::class);
        $error = new ApiError(OperationsErrorCode::IdempotencyInProgress, 409, 'Safe failure.', JsonDocuments::capture([]), ErrorCategory::Conflict, true, '/a~1b');
        $outcome = StoredOperationOutcome::failure($error);
        $json = $outcome->canonicalJson();
        $restored = StoredOperationOutcome::fromCanonicalJson($json, $registry);

        $this->assertSame($json, $restored->canonicalJson());
        $this->assertEquals($error->errorDescriptor(), $restored->error($registry)->errorDescriptor());
        $this->assertSame(OperationsErrorCode::IdempotencyInProgress, $restored->error($registry)->errorDescriptor()->errorCode);
    }

    public function test_unknown_code_and_corrupt_occurrence_become_safe_registered_errors(): void
    {
        $registry = $this->app->make(ProblemRegistry::class);
        $outcome = StoredOperationOutcome::failure(new ApiError(OperationsErrorCode::Forbidden, 403, 'Safe failure.'));
        $payload = json_decode($outcome->canonicalJson(), true, flags: JSON_THROW_ON_ERROR);
        foreach ([['code', 'attacker.secret'], ['category', 'bad'], ['status', 200], ['path', '/bad~2']] as [$key, $value]) {
            $candidate = $payload;
            $candidate['error'][$key] = $value;
            $candidate['error']['message'] = 'private SQLSTATE secret';
            $restored = StoredOperationOutcome::fromCanonicalJson(JsonCanonical::encode($candidate), $registry)->error($registry);
            $this->assertSame('core.internal_error', $restored->errorCode);
            $this->assertSame(500, $restored->status);
            $this->assertStringNotContainsString('secret', $restored->getMessage());
            $registry->assertOccurrence($restored->errorDescriptor(), $restored->status);
        }
    }

    public function test_queue_serialization_restores_the_registered_enum_identity(): void
    {
        $error = new ApiError(OperationsErrorCode::Forbidden, 403, 'Safe failure.');
        $restored = unserialize(serialize($error));
        $this->assertInstanceOf(ApiError::class, $restored);
        $this->assertEquals($error->errorDescriptor(), $restored->errorDescriptor());
        $this->app->make(ProblemRegistry::class)->assertOccurrence($restored->errorDescriptor(), $restored->status);
    }

    public function test_raw_string_cannot_construct_an_api_error(): void
    {
        $this->expectException(TypeError::class);
        $raw = 'operations.forbidden';
        new ApiError($raw, 403, 'Safe failure.');
    }
}
