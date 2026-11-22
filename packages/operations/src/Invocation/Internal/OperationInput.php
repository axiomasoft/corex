<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use Illuminate\Contracts\Validation\Factory;
use Packman\Attributes\Internal;

#[Internal]
final readonly class OperationInput
{
    public function __construct(private Factory $validation) {}

    public function normalize(OperationInvocation $invocation): OperationInvocation
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $invocation->operation->path, $matches);
        $required = $matches[1];

        if (array_diff($required, array_keys($invocation->parameters)) !== []
            || array_diff(array_keys($invocation->parameters), $required) !== []) {
            throw new ApiError(errorCode: OperationsErrorCode::InvalidRequest, status: 422, message: 'Invalid operation parameters.');
        }
        foreach ($invocation->parameters as $name => $value) {
            if (preg_match('~\A[^/\x00-\x1f\x7f]{1,512}\z~uD', $value) !== 1) {
                throw new ApiError(errorCode: OperationsErrorCode::InvalidRequest, status: 422,
                    message: 'Invalid operation parameter.', path: self::pointer([$name]));
            }
        }
        $input = JsonDocuments::array(value: $invocation->input);
        $rules = $invocation->operation->inputRules;
        $declared = [];
        foreach ($rules as $key => $_rule) {
            $declared[explode('.', $key)[0]] = true;
        }
        $extra = array_diff_key($input, $declared);

        if ($extra !== []) {
            $names = array_map(strval(...), array_keys($extra));
            sort($names, SORT_STRING);

            throw new ApiError(errorCode: OperationsErrorCode::UndeclaredFields, status: 422,
                message: 'Input contains undeclared fields.', details: JsonDocuments::capture(['fields' => $names]), path: self::pointer([$names[0]]));
        }

        $validator = $this->validation->make($input, $rules);

        if ($validator->fails()) {
            $paths = array_keys($validator->failed());
            sort($paths, SORT_STRING);
            $field = $paths[0] ?? null;

            throw new ApiError(errorCode: OperationsErrorCode::ValidationFailed, status: 422,
                message: 'Input validation failed.', path: is_string($field) ? self::pointer(explode('.', $field)) : null);
        }

        $normalized = $validator->validated();
        foreach ($rules as $field => $rule) {
            if (! isset($normalized[$field])) {
                continue;
            }

            $normalized[$field] = match (true) {
                in_array('integer', $rule, true) && is_numeric($normalized[$field]) => (int) $normalized[$field],
                in_array('boolean', $rule, true) => (bool) $normalized[$field],
                default => $normalized[$field],
            };
        }

        return new OperationInvocation(
            operation: $invocation->operation,
            context: $invocation->context,
            input: JsonDocuments::capture($normalized),
            parameters: $invocation->parameters,
            idempotencyKey: $invocation->idempotencyKey,
            clientId: $invocation->clientId,
        );
    }

    /** @param list<string> $parts */
    private static function pointer(array $parts): string
    {
        return '/'.implode('/', array_map(static fn (string $part): string => str_replace(['~', '/'], ['~0', '~1'], $part), $parts));
    }
}
