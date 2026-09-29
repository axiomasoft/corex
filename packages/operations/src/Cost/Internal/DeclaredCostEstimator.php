<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Internal;

use CoreX\Operations\Cost\Contracts\OperationCostEstimator;
use CoreX\Operations\Cost\Data\ActualCost;
use CoreX\Operations\Cost\Data\CostInput;
use CoreX\Operations\Cost\Data\EstimatedCost;
use CoreX\Operations\Definition\Enums\OperationMode;
use CoreX\Operations\Definition\Operation;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Validation\JsonCanonical;

/** Pure declared-recipe estimator. No database, cache, network or handler reflection. */
final readonly class DeclaredCostEstimator implements OperationCostEstimator
{
    /** @var array<string, int> */
    private const DEFAULTS = [
        'base' => 1,
        'row' => 0,
        'field' => 0,
        'relation' => 0,
        'attribute_filter' => 0,
        'highlight' => 0,
        'side_effect' => 0,
        'max_units' => 10000,
    ];

    public function estimate(Operation $operation, CostInput $input): EstimatedCost
    {
        $recipe = $this->recipe($operation);
        $units = $this->units($operation, $input, $recipe);

        return new EstimatedCost(
            units: $units,
            recipeHash: hash('sha256', JsonCanonical::encode($recipe)),
        );
    }

    public function actual(Operation $operation, CostInput $observed): ActualCost
    {
        return new ActualCost(units: $this->units($operation, $observed, $this->recipe($operation)));
    }

    /** @return array<string, int> */
    private function recipe(Operation $operation): array
    {
        $recipe = self::DEFAULTS;

        foreach ($operation->cost ?? [] as $key => $value) {
            if (array_key_exists($key, $recipe)) {
                $recipe[$key] = $value;
            }
        }

        return $recipe;
    }

    /** @param  array<string, int>  $recipe */
    private function units(Operation $operation, CostInput $input, array $recipe): int
    {
        $pageRows = $input->pageRows > 0 ? $input->pageRows : $this->declaredPageMaximum($operation);
        $exportRows = $input->exportRows;

        if ($exportRows === 0 && $recipe['row'] > 0 && in_array($operation->mode, [OperationMode::BulkQuery, OperationMode::BulkCommand], true) && $pageRows === 0) {
            throw new ApiError('validation_failed', 422, 'Export requires a bounded row count.');
        }

        $units = $recipe['base'];
        $units = $this->add($units, $this->multiply($recipe['row'], max($pageRows, $exportRows)));
        $units = $this->add($units, $this->multiply($recipe['field'], $input->selectedFields));
        $units = $this->add($units, $this->multiply($recipe['relation'], $input->relationNodes));
        $units = $this->add($units, $this->multiply($recipe['attribute_filter'], $input->attributeFilters));
        $units = $this->add($units, $this->multiply($recipe['highlight'], $input->highlight ? 1 : 0));
        $units = $this->add($units, $this->multiply($recipe['side_effect'], $input->sideEffects));

        if ($units > $recipe['max_units']) {
            throw new ApiError('validation_failed', 422, 'Operation cost exceeds the declared maximum.');
        }

        return $units;
    }

    private function declaredPageMaximum(Operation $operation): int
    {
        foreach (['max', 'max_rows', 'limit'] as $key) {
            $value = $operation->pagination[$key] ?? null;

            if (is_int($value) && $value >= 0) {
                return $value;
            }
        }

        return 0;
    }

    private function add(int $left, int $right): int
    {
        if ($right > 0 && $left > PHP_INT_MAX - $right) {
            throw new ApiError('validation_failed', 422, 'Operation cost overflow.');
        }

        return $left + $right;
    }

    private function multiply(int $left, int $right): int
    {
        if ($left === 0 || $right === 0) {
            return 0;
        }

        if ($right > 0 && $left > intdiv(PHP_INT_MAX, $right)) {
            throw new ApiError('validation_failed', 422, 'Operation cost overflow.');
        }

        return $left * $right;
    }
}
