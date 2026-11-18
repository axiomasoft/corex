<?php

declare(strict_types=1);

use CoreX\CustomFields\Internal\SchemaOperationConflict;
use CoreX\CustomFields\Internal\ScopeDenied;
use CoreX\CustomFields\Internal\UnsupportedAttributeCapability;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Enums\UnavailabilityReason;

it('describes an unavailable attributes engine without parsing the message', function (): void {
    $error = new UnsupportedAttributeCapability;
    $descriptor = $error->errorDescriptor();

    expect($descriptor->code)->toBe('custom-fields.attributes_engine_unavailable')
        ->and($descriptor->category)->toBe(ErrorCategory::Unavailable)
        ->and($descriptor->retryable)->toBeFalse()
        ->and($descriptor->details)->toBe(['reason' => UnavailabilityReason::CapabilityUnavailable->value])
        ->and($error->getMessage())->toBe('No qualified CoreX attributes engine is installed.')
        ->and($descriptor->code)->not->toBe($error->getMessage());
});

it('describes access denial independently of the human message', function (string $message): void {
    $error = new ScopeDenied($message);
    $descriptor = $error->errorDescriptor();

    expect($descriptor->code)->toBe('custom-fields.attributes_access_denied')
        ->and($descriptor->category)->toBe(ErrorCategory::Authorization)
        ->and($descriptor->retryable)->toBeFalse()
        ->and($descriptor->details)->toBe(['reason' => UnavailabilityReason::AccessDenied->value])
        ->and($error->getMessage())->toBe($message);
})->with([
    'scope' => 'The current scope denies this schema operation.',
    'connection' => 'The attribute operation targets a different database connection.',
    'unrelated' => 'human text is not a machine contract',
]);

it('owns schema conflict constructors without message branching', function (): void {
    $notReady = SchemaOperationConflict::notReady();
    $stale = SchemaOperationConflict::staleSnapshot();
    $exhausted = SchemaOperationConflict::retryExhausted();

    expect($notReady->errorDescriptor()->code)->toBe('custom-fields.attribute_schema_conflict')
        ->and($notReady->errorDescriptor()->category)->toBe(ErrorCategory::Conflict)
        ->and($notReady->errorDescriptor()->retryable)->toBeTrue()
        ->and($notReady->errorDescriptor()->details)->toBe(['reason' => 'not_ready'])
        ->and($stale->errorDescriptor()->code)->toBe('custom-fields.attribute_schema_conflict')
        ->and($stale->errorDescriptor()->retryable)->toBeFalse()
        ->and($stale->errorDescriptor()->details)->toBe(['reason' => 'stale_snapshot'])
        ->and($exhausted->errorDescriptor()->code)->toBe('custom-fields.attribute_schema_retry_exhausted')
        ->and($exhausted->errorDescriptor()->category)->toBe(ErrorCategory::Conflict)
        ->and($exhausted->errorDescriptor()->retryable)->toBeFalse()
        ->and($exhausted->errorDescriptor()->details)->toBe(['reason' => 'retry_exhausted'])
        ->and($notReady->errorDescriptor()->code)->not->toBe($notReady->getMessage())
        ->and($stale->errorDescriptor()->details['reason'])->not->toBe($stale->getMessage())
        ->and($exhausted->errorDescriptor()->code)->not->toBe($exhausted->getMessage());
});
