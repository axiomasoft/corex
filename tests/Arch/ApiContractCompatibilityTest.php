<?php

declare(strict_types=1);

// the Arch test invokes the compatibility CLI. It does not reimplement the comparator.

test('api contract gate accepts the committed snapshot and additive fixtures', function (): void {
    $repoRoot = dirname(__DIR__, 2);

    $committed = runContractGate($repoRoot);
    $allowed = runContractGate(
        $repoRoot,
        $repoRoot.'/tests/fixtures/api-contract/baseline',
        $repoRoot.'/tests/fixtures/api-contract/allowed-addition',
    );

    $webhookAllowed = runContractGate(
        $repoRoot,
        $repoRoot.'/tests/fixtures/api-contract/webhook-baseline',
        $repoRoot.'/tests/fixtures/api-contract/webhook-allowed-addition',
    );

    expect($committed['exit'])->toBe(0, $committed['output'])
        ->and($committed['output'])->toContain('api-contract: OK')
        ->and($allowed['exit'])->toBe(0, $allowed['output'])
        ->and($allowed['output'])->toContain('api-contract: OK')
        ->and($webhookAllowed['exit'])->toBe(0, $webhookAllowed['output'])
        ->and($webhookAllowed['output'])->toContain('api-contract: OK');
});

test('the v2 contract check refuses an incomplete or stale carrier set', function (string $fault, string $violation): void {
    $repoRoot = dirname(__DIR__, 2);
    $directory = sys_get_temp_dir().'/corex-api-contract-'.bin2hex(random_bytes(8));
    mkdir($directory);

    try {
        foreach (glob($repoRoot.'/api/v2/*.json') ?: [] as $path) {
            copy($path, $directory.'/'.basename($path));
        }

        $carrier = $directory.'/webhooks.json';

        if ($fault === 'missing') {
            unlink($carrier);
        } else {
            file_put_contents($carrier, "\n", FILE_APPEND);
        }

        $result = runContractGate($repoRoot, $repoRoot.'/api/v2', $directory);

        expect($result['exit'])->toBe(1, $result['output'])
            ->and($result['output'])->toContain($violation);
    } finally {
        foreach (glob($directory.'/*.json') ?: [] as $path) {
            unlink($path);
        }
        rmdir($directory);
    }
})->with([
    'missing carrier' => ['missing', 'snapshot-file-set manifest.json /files'],
    'stale digest' => ['stale', 'snapshot-digest manifest.json /files/webhooks.json'],
]);

test('api contract gate rejects each forbidden intra-major delta', function (string $candidate, string $rule, string $pointer, string $baseline = 'baseline'): void {
    $repoRoot = dirname(__DIR__, 2);
    $result = runContractGate(
        $repoRoot,
        $repoRoot.'/tests/fixtures/api-contract/'.$baseline,
        $repoRoot.'/tests/fixtures/api-contract/'.$candidate,
    );

    expect($result['exit'])->toBe(1, $result['output'])
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
    'error contract drift' => ['error-contract-drift', 'error-contract-drift', '/errors/axiomasoft~1corex-operations/operations.access_denied/retryable'],
    'pagination reinterpretation' => ['pagination-reinterpretation', 'pagination-reinterpretation', '/operations/notes.list/pagination/ordering'],
    'topic removal' => ['topic-removal', 'topic-removal', '/topics/orders.placed.v1', 'webhook-baseline'],
    'required payload addition' => ['required-payload-addition', 'required-input-addition', '/topics/orders.placed.v1/payload/required', 'webhook-baseline'],
    'payload response field removal' => ['payload-field-removal', 'response-field-removal', '/topics/orders.placed.v1/payload/properties/id', 'webhook-baseline'],
    'payload type change' => ['payload-type-change', 'type-change', '/topics/orders.placed.v1/payload/properties/id/type', 'webhook-baseline'],
    'payload nullability change' => ['payload-nullability-change', 'nullability-change', '/topics/orders.placed.v1/payload/properties/note/nullable', 'webhook-baseline'],
    'pii widening' => ['pii-widening', 'pii-widening', '/topics/orders.placed.v1/pii_class', 'webhook-baseline'],
    'signature semantics' => ['signature-semantics', 'signature-semantics', '/rotation/algorithm', 'webhook-baseline'],
    'id semantics' => ['id-semantics', 'id-semantics', '/rotation/id_semantics', 'webhook-baseline'],
    'major rebind' => ['major-rebind', 'major-rebind', '/topics/orders.placed.v1/major', 'webhook-baseline'],
    'cost default enforcement' => ['cost-default-enforcement', 'cost-default-enforcement', '/operations/notes.read/cost/enforcement', 'webhook-baseline'],
]);

test('api contract check refuses to update the baseline', function (): void {
    $repoRoot = dirname(__DIR__, 2);
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/check-api-contract.php').' --update 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    expect($exitCode)->toBe(2, "gate exit {$exitCode}:\n".implode("\n", $output))
        ->and(implode("\n", $output))->toContain('baseline update is not a check action');
});

/**
 * @return array{exit: int, output: string}
 */
function runContractGate(string $repoRoot, ?string $baseline = null, ?string $candidate = null): array
{
    $command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($repoRoot.'/scripts/check-api-contract.php');

    if ($baseline !== null && $candidate !== null) {
        $command .= ' --baseline '.escapeshellarg($baseline).' --candidate '.escapeshellarg($candidate);
    }
    $command .= ' 2>&1';
    $output = [];
    $exitCode = 0;

    exec($command, $output, $exitCode);

    return ['exit' => $exitCode, 'output' => implode("\n", $output)];
}
