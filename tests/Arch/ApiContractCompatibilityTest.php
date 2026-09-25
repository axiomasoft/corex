<?php

declare(strict_types=1);

// P13.11 — the Arch test invokes the compatibility CLI. It does not reimplement the comparator.

test('api contract gate accepts the committed snapshot and additive fixtures', function (): void {
    $repoRoot = dirname(__DIR__, 2);

    $committed = runContractGate($repoRoot, $repoRoot.'/api/v1', $repoRoot.'/api/v1');
    $allowed = runContractGate(
        $repoRoot,
        $repoRoot.'/tests/fixtures/api-contract/baseline',
        $repoRoot.'/tests/fixtures/api-contract/allowed-addition',
    );

    expect($committed['exit'])->toBe(0)
        ->and($committed['output'])->toContain('api-contract: OK')
        ->and($allowed['exit'])->toBe(0)
        ->and($allowed['output'])->toContain('api-contract: OK');
});

test('api contract gate rejects each forbidden intra-major delta', function (string $candidate, string $rule, string $pointer): void {
    $repoRoot = dirname(__DIR__, 2);
    $result = runContractGate(
        $repoRoot,
        $repoRoot.'/tests/fixtures/api-contract/baseline',
        $repoRoot.'/tests/fixtures/api-contract/'.$candidate,
    );

    expect($result['exit'])->toBe(1)
        ->and($result['output'])->toContain($rule)
        ->and($result['output'])->toContain($pointer)
        ->and($result['output'])->toContain('api-contract: FAIL');
})->with([
    'operation removal' => ['operation-removal', 'operation-removal', '/operations/notes.read'],
    'path reassignment' => ['path-reassignment', 'operation-removal', '/operations/notes.read/path'],
    'response field removal' => ['response-field-removal', 'response-field-removal', '/operations/notes.read/response_schema/properties/title'],
    'required input addition' => ['required-input-addition', 'required-input-addition', '/operations/notes.read/request_schema/required'],
    'type change' => ['type-change', 'type-change', '/operations/notes.read/response_schema/properties/title/type'],
    'nullability change' => ['nullability-change', 'nullability-change', '/operations/notes.read/response_schema/properties/status/nullable'],
    'enum removal' => ['enum-removal', 'enum-removal', '/operations/notes.read/response_schema/properties/status/enum'],
    'error contract drift' => ['error-contract-drift', 'error-contract-drift', '/errors/corex~1api/access_denied/retryable'],
    'pagination reinterpretation' => ['pagination-reinterpretation', 'pagination-reinterpretation', '/operations/notes.list/pagination/ordering'],
]);

test('api contract check refuses to update the baseline', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/check-api-contract.php').' --update 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(2)
        ->and(implode("\n", $output))->toContain('baseline update is not a check action');
});

/**
 * @return array{exit: int, output: string}
 */
function runContractGate(string $repoRoot, string $baseline, string $candidate): array
{
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/check-api-contract.php')
        .' --baseline '.escapeshellarg($baseline)
        .' --candidate '.escapeshellarg($candidate)
        .' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    return ['exit' => $exitCode, 'output' => implode("\n", $output)];
}
