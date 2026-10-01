<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Internal;

use CoreX\Modules\Data\Api;
use CoreX\Modules\Exceptions\CompilationException;
use JsonException;

/** @internal Validates serializable declarations without depending on an API runtime. */
final class ApiOperationValidator
{
    public static function validate(Api $api): void
    {
        if (preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]*$/D', $api->operationId) !== 1
            || preg_match('/^v[1-9][0-9]*$/D', $api->version) !== 1
            || ! in_array($api->method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)
            || preg_match('#^/(?:[a-zA-Z0-9_.-]+|\{[a-zA-Z_][a-zA-Z0-9_]*\})(?:/(?:[a-zA-Z0-9_.-]+|\{[a-zA-Z_][a-zA-Z0-9_]*\}))*$#D', $api->path) !== 1
            || trim($api->ability) === '' || $api->handler === null
            || ! class_exists($api->handler)
            || $api->successStatus < 200 || $api->successStatus > 299 || $api->successStatus === 204) {
            throw new CompilationException('Invalid API operation declaration: '.$api->operationId);
        }

        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $api->path, $parameters);

        if (count($parameters[1]) !== count(array_unique($parameters[1]))) {
            throw new CompilationException('Repeated API path parameter: '.$api->operationId);
        }

        self::validateRules($api->inputRules);
        self::normalizeLegacyContract($api);
        self::validateContract($api);

        self::assertJsonValue($api->requestSchema);
        self::assertJsonValue($api->responseSchema);

        try {
            json_encode([$api->requestSchema, $api->responseSchema], JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CompilationException('Invalid API schema: '.$api->operationId, previous: $e);
        }
    }

    public static function validateRoutePair(Api $first, Api $second): void
    {
        if ($first->version !== $second->version) {
            return;
        }

        $normalize = static fn (string $path): string => (string) preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '{}', $path);

        // OpenAPI permits one template at a hierarchy, regardless of HTTP method.
        if ($normalize($first->path) === $normalize($second->path) && $first->path !== $second->path) {
            throw new CompilationException('Equivalent API templates must use the same parameter names.');
        }

        if ($first->method !== $second->method || substr_count($first->path, '{') !== substr_count($second->path, '{')) {
            return;
        }

        $firstSegments = explode('/', $first->path);
        $secondSegments = explode('/', $second->path);

        if (count($firstSegments) !== count($secondSegments)) {
            return;
        }

        foreach ($firstSegments as $index => $segment) {
            if ($segment !== $secondSegments[$index] && ! str_starts_with($segment, '{') && ! str_starts_with($secondSegments[$index], '{')) {
                return;
            }
        }

        throw new CompilationException('Ambiguous API routes with equal specificity.');
    }

    private static function normalizeLegacyContract(Api $api): void
    {
        if ($api->operationModeExplicit) {
            return;
        }

        $mutation = in_array($api->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $api->operationMode = $mutation ? 'command' : 'query';

        if (! $api->idempotencyExplicit) {
            $api->idempotencyRequirement = $mutation ? 'optional' : 'forbidden';
        }
    }

    private static function validateContract(Api $api): void
    {
        $owner = $api->declaringModule ?? $api->operationId;
        $mode = $api->operationMode;
        $method = $api->method;
        $idempotency = $api->idempotencyRequirement;
        $allowed = [
            'query' => ['GET' => ['forbidden']],
            'command' => ['POST' => ['optional', 'required'], 'PUT' => ['optional', 'required'], 'PATCH' => ['optional', 'required'], 'DELETE' => ['optional', 'required']],
            'async_command' => ['POST' => ['required']],
            'bulk_query' => ['POST' => ['required']],
            'bulk_command' => ['POST' => ['required']],
        ];

        if (! isset($allowed[$mode][$method]) || ! in_array($idempotency, $allowed[$mode][$method], true)) {
            throw new CompilationException("Illegal operation contract [{$api->operationId}] owned by [{$owner}]: {$mode}/{$method}/{$idempotency}.");
        }

        if ($mode === 'async_command' && $api->successStatus !== 202) {
            throw new CompilationException("Async command [{$api->operationId}] owned by [{$owner}] requires success status 202.");
        }

        if (in_array($mode, ['bulk_query', 'bulk_command'], true)) {
            $api->executionSupported = false;

            if ($api->bulkExecutionEnabled) {
                throw new CompilationException("Bulk operation [{$api->operationId}] owned by [{$owner}] cannot enable execution.");
            }
        }

        self::assertUniqueStrings($api->requiredScopes, "duplicate scope on [{$api->operationId}] owned by [{$owner}]");
        self::assertUniqueStrings($api->capabilities, "duplicate capability on [{$api->operationId}] owned by [{$owner}]");
        self::assertFieldList($api->readFields, $api->operationId, $owner, 'read');
        self::assertFieldList($api->writeFields, $api->operationId, $owner, 'write');

        foreach ($api->capabilities as $capability) {
            if (preg_match('/^[a-z][a-z0-9_.-]*\.v[1-9][0-9]*$/D', $capability) !== 1) {
                throw new CompilationException("Invalid capability [{$capability}] on [{$api->operationId}] owned by [{$owner}].");
            }
        }

        if ($api->pagination !== null) {
            self::assertPagination($api->pagination, $api->operationId, $owner);
        }

        if ($api->deprecation !== null) {
            self::assertDeprecation($api->deprecation, $api->operationId, $owner);
        }

        self::validateCost($api, $owner);

        $idempotentMutation = $mode !== 'query' && in_array($idempotency, ['optional', 'required'], true);

        if ($idempotentMutation && $api->idempotencyExplicit && ! in_array($api->effectMode, ['tenant_transactional', 'same_transaction_outbox', 'reconciliation_contract'], true)) {
            throw new CompilationException("Unsafe idempotent effect mode on [{$api->operationId}] owned by [{$owner}].");
        }

        if ($api->effectMode !== null && ! in_array($api->effectMode, ['tenant_transactional', 'same_transaction_outbox', 'reconciliation_contract'], true)) {
            throw new CompilationException("Unsafe idempotent effect mode on [{$api->operationId}] owned by [{$owner}].");
        }
    }

    /** @var array<string, int> */
    private const COST_DEFAULTS = [
        'base' => 1,
        'row' => 0,
        'field' => 0,
        'relation' => 0,
        'attribute_filter' => 0,
        'highlight' => 0,
        'side_effect' => 0,
        'max_units' => 10000,
    ];

    private static function validateCost(Api $api, string $owner): void
    {
        $bulk = in_array($api->operationMode, ['bulk_query', 'bulk_command'], true);

        if (! $api->costExplicit) {
            if ($bulk) {
                throw new CompilationException("Bulk operation [{$api->operationId}] owned by [{$owner}] requires a cost recipe.");
            }

            $api->cost = null;

            return;
        }

        $recipe = $api->cost ?? [];

        if ($recipe !== [] && array_is_list($recipe)) {
            throw new CompilationException("Unknown cost key on [{$api->operationId}] owned by [{$owner}].");
        }

        $canonical = self::COST_DEFAULTS;

        foreach ($recipe as $key => $value) {
            if (! is_string($key) || ! array_key_exists($key, $canonical)) {
                throw new CompilationException("Unknown cost key on [{$api->operationId}] owned by [{$owner}].");
            }

            if (! is_int($value)) {
                throw new CompilationException("Cost overflow on [{$api->operationId}] owned by [{$owner}].");
            }

            if ($value < 0) {
                throw new CompilationException("Negative cost weight on [{$api->operationId}] owned by [{$owner}].");
            }

            if ($value > 10000 || (in_array($key, ['base', 'max_units'], true) && $value < 1)) {
                throw new CompilationException("Cost overflow on [{$api->operationId}] owned by [{$owner}].");
            }

            $canonical[$key] = $value;
        }

        $ordered = [];

        foreach (array_keys(self::COST_DEFAULTS) as $key) {
            $ordered[$key] = $canonical[$key];
        }

        $api->cost = $ordered;
    }

    /** @param  array<mixed>  $values */
    private static function assertUniqueStrings(array $values, string $message): void
    {
        foreach ($values as $value) {
            if (! is_string($value) || $value === '') {
                throw new CompilationException($message);
            }
        }

        if (count($values) !== count(array_unique($values))) {
            throw new CompilationException($message);
        }
    }

    /** @param  array<mixed>|null  $fields */
    private static function assertFieldList(?array $fields, string $operationId, string $owner, string $side): void
    {
        if ($fields === null) {
            return;
        }

        if (! array_is_list($fields)) {
            throw new CompilationException("Invalid {$side} fields on [{$operationId}] owned by [{$owner}].");
        }

        self::assertUniqueStrings($fields, "duplicate {$side} field on [{$operationId}] owned by [{$owner}]");
    }

    /** @param  array<mixed>  $pagination */
    private static function assertPagination(array $pagination, string $operationId, string $owner): void
    {
        if (array_is_list($pagination)) {
            throw new CompilationException("Invalid pagination on [{$operationId}] owned by [{$owner}].");
        }

        foreach ($pagination as $key => $value) {
            if (! is_string($key) || $key === '' || ! self::isScalarOrList($value)) {
                throw new CompilationException("Invalid pagination on [{$operationId}] owned by [{$owner}].");
            }
        }
    }

    /** @param  array<mixed>  $deprecation */
    private static function assertDeprecation(array $deprecation, string $operationId, string $owner): void
    {
        $keys = array_keys($deprecation);
        sort($keys);

        if ($keys !== ['deprecatedAt', 'id', 'migrationUri', 'successor', 'sunsetAt']) {
            throw new CompilationException("Invalid deprecation on [{$operationId}] owned by [{$owner}].");
        }

        foreach (['id', 'deprecatedAt', 'migrationUri'] as $required) {
            if (! is_string($deprecation[$required]) || $deprecation[$required] === '') {
                throw new CompilationException("Invalid deprecation on [{$operationId}] owned by [{$owner}].");
            }
        }

        foreach (['successor', 'sunsetAt'] as $optional) {
            if ($deprecation[$optional] !== null && (! is_string($deprecation[$optional]) || $deprecation[$optional] === '')) {
                throw new CompilationException("Invalid deprecation on [{$operationId}] owned by [{$owner}].");
            }
        }
    }

    private static function isScalarOrList(mixed $value): bool
    {
        if (is_array($value)) {
            if (! array_is_list($value)) {
                return false;
            }

            foreach ($value as $item) {
                if (! is_scalar($item)) {
                    return false;
                }
            }

            return true;
        }

        return is_scalar($value);
    }

    private static function validateRules(mixed $rules): void
    {
        if (! is_array($rules)) {
            throw new CompilationException('API validation rules must be an array.');
        }

        foreach ($rules as $field => $list) {
            if (! is_string($field) || $field === '' || ! is_array($list) || ! array_is_list($list)) {
                throw new CompilationException('Invalid API validation field or rule list.');
            }

            foreach ($list as $rule) {
                if (! is_string($rule)) {
                    throw new CompilationException('API validation rules must be strings.');
                }
            }
        }
    }

    public static function assertJsonValue(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertJsonValue($item);
            }
        } elseif (! is_scalar($value) && $value !== null) {
            throw new CompilationException('API schemas must contain only JSON values.');
        }
    }
}
