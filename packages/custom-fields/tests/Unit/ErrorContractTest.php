<?php

declare(strict_types=1);

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use CoreX\CustomFields\Errors\Exceptions\FieldFailure;
use CoreX\CustomFields\Errors\Internal\PackageProblemDefinitions;

it('publishes each structured failure in the owner problem registry', function (): void {
    $catalogue = array_column(iterator_to_array((new PackageProblemDefinitions)->definitions()), 'code');
    foreach (CustomFieldsErrorCode::cases() as $code) {
        $descriptor = (new FieldFailure($code, '/operations/0/input'))->errorDescriptor();
        expect($catalogue)->toContain($code)->and($descriptor->code)->toBe($code->value)
            ->and($descriptor->category)->toBe($code->category())->and($descriptor->path)->toBe('/operations/0/input');
    }
});
