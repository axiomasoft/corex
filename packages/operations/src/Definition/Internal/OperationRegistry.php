<?php

declare(strict_types=1);

namespace CoreX\Operations\Definition\Internal;

use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Laravel\OperationFactory;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use LogicException;

final readonly class OperationRegistry implements \CoreX\Operations\Definition\Contracts\OperationRegistry
{
    public function __construct(private ModuleRegistry $modules) {}

    /** @return list<Operation> Installed code catalogue; activation and authorization are checked on every dispatch. */
    public function all(string $version): array
    {
        $operations = [];
        $keys = [];

        foreach ($this->modules->compiled()->api as $api) {
            if ($api->mode !== 'operation' || $api->version !== $version) {
                continue;
            }

            $operation = OperationFactory::fromDeclaration(api: $api, registryFingerprint: $this->modules->compiled()->fingerprint);
            $key = $operation->method.':'.preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '{}', $operation->path);

            if (isset($keys[$key]) || isset($keys['id:'.$operation->id])) {
                throw new LogicException('Duplicate compiled API operation.');
            }

            $keys[$key] = true;
            $keys['id:'.$operation->id] = true;
            $operations[] = $operation;
        }

        // Literal routes precede parameter routes; the remaining order is deterministic.
        usort($operations, static function (Operation $a, Operation $b): int {
            $parameters = substr_count($a->path, '{') <=> substr_count($b->path, '{');
            $path = strcmp($a->path, $b->path);

            return $parameters !== 0 ? $parameters : ($path !== 0 ? $path : strcmp($a->method, $b->method));
        });

        return $operations;
    }

    /** @return array{Operation, array<string, string>} */
    public function match(string $version, string $method, string $path): array
    {
        foreach ($this->all($version) as $operation) {
            if ($operation->method !== $method) {
                continue;
            }

            $names = [];
            $segments = [];

            foreach (explode('/', $operation->path) as $segment) {
                if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/D', $segment, $parameter) === 1) {
                    $names[] = $parameter[1];
                    $segments[] = '([^/]+)';
                } else {
                    $segments[] = preg_quote($segment, '#');
                }
            }

            if (preg_match('#^'.implode('/', $segments).'$#D', $path, $matches) === 1) {
                array_shift($matches);

                return [$operation, array_combine($names, $matches)];
            }
        }

        throw new ApiError(errorCode: OperationsErrorCode::OperationNotFound, status: 404, message: 'Operation not found.');
    }
}
