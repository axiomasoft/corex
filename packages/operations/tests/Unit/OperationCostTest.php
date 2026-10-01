<?php

declare(strict_types=1);

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Modules\Data\Api;
use CoreX\Operations\Cost\Data\CostInput;
use CoreX\Operations\Cost\Internal\DeclaredCostEstimator;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Laravel\OperationFactory;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Tests\Support\CostOperationHandler;
use CoreX\Operations\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(TestCase::class);

$schema = ['type' => 'object', 'additionalProperties' => false];

$project = static function (Api $api): Operation {
    $api->declaringModule = 'example/notes';

    return OperationFactory::fromDeclaration($api, 'fingerprint');
};

$input = static function (
    int $pageRows = 0,
    int $selectedFields = 0,
    int $relationNodes = 0,
    int $attributeFilters = 0,
    bool $highlight = false,
    int $exportRows = 0,
    int $sideEffects = 0,
): CostInput {
    return new CostInput(
        pageRows: $pageRows,
        selectedFields: $selectedFields,
        relationNodes: $relationNodes,
        attributeFilters: $attributeFilters,
        highlight: $highlight,
        exportRows: $exportRows,
        sideEffects: $sideEffects,
    );
};

it('estimates table-driven weights, page, export, field, relation, filter, highlight and side-effect cases', function () use ($schema, $project, $input): void {
    $estimator = new DeclaredCostEstimator;

    $cases = [
        'legacy base' => [null, $input(), 1],
        'page rows' => [['row' => 2], $input(pageRows: 10), 21],
        'export rows' => [['row' => 3], $input(exportRows: 4), 13],
        'export dominates page' => [['row' => 2], $input(pageRows: 3, exportRows: 10), 21],
        'declared page maximum' => [['row' => 2], $input(), 11, ['max' => 5]],
        'fields' => [['field' => 3], $input(selectedFields: 4), 13],
        'relations' => [['relation' => 5], $input(relationNodes: 2), 11],
        'attribute filters' => [['attribute_filter' => 4], $input(attributeFilters: 3), 13],
        'highlight on' => [['highlight' => 7], $input(highlight: true), 8],
        'highlight off' => [['highlight' => 7], $input(), 1],
        'side effects' => [['side_effect' => 9], $input(sideEffects: 2), 19],
        'combined' => [['base' => 2, 'row' => 1, 'field' => 1, 'relation' => 1, 'attribute_filter' => 1, 'highlight' => 1, 'side_effect' => 1], $input(pageRows: 3, selectedFields: 2, relationNodes: 4, attributeFilters: 5, highlight: true, sideEffects: 6), 23],
    ];

    foreach ($cases as $name => $case) {
        [$recipe, $costInput, $expected] = $case;
        $pagination = $case[3] ?? null;
        $api = Api::operation('notes.list', 'GET', '/notes', CostOperationHandler::class, 'notes.read', $schema, $schema);

        if (is_array($recipe)) {
            $api->cost($recipe);
        }

        if (is_array($pagination)) {
            $api->paginate($pagination);
        }

        $estimated = $estimator->estimate($project($api), $costInput);

        expect($estimated->units)->toBe($expected, $name)
            ->and($estimator->actual($project($api), $costInput)->units)->toBe($expected, $name);
    }
});

it('denies overflow and absent bounded export', function () use ($schema, $project, $input): void {
    $estimator = new DeclaredCostEstimator;

    $overflow = $project(
        Api::operation('notes.list', 'GET', '/notes', CostOperationHandler::class, 'notes.read', $schema, $schema)
            ->cost(['row' => 2, 'max_units' => 10]),
    );

    expect(fn () => $estimator->estimate($overflow, $input(pageRows: 6)))
        ->toThrow(ApiError::class, 'Operation cost exceeds the declared maximum.');

    try {
        $estimator->estimate($overflow, $input(pageRows: intdiv(PHP_INT_MAX, 2) + 1));
        expect(false)->toBeTrue('Expected integer overflow denial.');
    } catch (ApiError $error) {
        expect($error->errorCode)->toBe('operations.validation_failed')
            ->and($error->getMessage())->toBe('Operation cost overflow.');
    }

    $unbounded = $project(
        Api::operation('notes.export', 'POST', '/notes/export', CostOperationHandler::class, 'notes.read', $schema, $schema)
            ->asBulkQuery()
            ->idempotency('required')
            ->effect('tenant_transactional')
            ->cost(['row' => 1]),
    );

    expect(fn () => $estimator->estimate($unbounded, $input()))
        ->toThrow(ApiError::class, 'Export requires a bounded row count.');

    expect($estimator->estimate($unbounded, $input(exportRows: 4))->units)->toBe(5);
});

it('does not call database, cache or network while estimating', function () use ($schema, $project, $input): void {
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    Http::fake();
    Cache::put('cost-sentinel', 'untouched');

    $operation = $project(
        Api::operation('notes.list', 'GET', '/notes', CostOperationHandler::class, 'notes.read', $schema, $schema)
            ->cost(['row' => 2, 'field' => 1]),
    );

    $estimated = (new DeclaredCostEstimator)->estimate($operation, $input(pageRows: 3, selectedFields: 2));

    expect($estimated->units)->toBe(9)
        ->and($queries)->toBe(0)
        ->and(Cache::get('cost-sentinel'))->toBe('untouched');
    Http::assertNothingSent();
});

it('keeps old positional constructors working and hashes cost additively', function () use ($schema, $project): void {
    $positional = new Operation(
        'example/notes',
        'notes.list',
        'v1',
        'GET',
        '/notes',
        'notes.read',
        CostOperationHandler::class,
        JsonDocuments::capture($schema),
        JsonDocuments::capture($schema),
        [],
        200,
    );

    expect($positional->cost)->toBeNull()
        ->and($positional->executionSupported)->toBeTrue()
        ->and($positional->id)->toBe('notes.list');

    $legacy = $project(Api::operation('notes.list', 'GET', '/notes', CostOperationHandler::class, 'notes.read', $schema, $schema));
    $priced = $project(Api::operation('notes.list', 'GET', '/notes', CostOperationHandler::class, 'notes.read', $schema, $schema)->cost(['base' => 1]));

    expect($legacy->cost)->toBeNull()
        ->and($priced->cost['base'] ?? null)->toBe(1)
        ->and($legacy->contractHash)->not->toBe($priced->contractHash);
});
