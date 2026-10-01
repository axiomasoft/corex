<?php

declare(strict_types=1);

use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Internal\ErrorSourceScanner;
use CoreX\Tests\Support\SourceScanner;

test('ErrorContract package catalogue matches the complete stable fixture', function (): void {
    $root = SourceScanner::repoRoot();
    $fixture = require $root.'/tests/fixtures/public-error-contract.php';
    $scanner = new ErrorSourceScanner;
    $registry = $scanner->catalogue($root);
    $actual = [];
    foreach ($registry->all() as $definition) {
        $actual[] = ['code' => $definition->code->value(), 'enum' => $definition->code::class, 'type' => $definition->type, 'status' => $definition->defaultStatus, 'title' => $definition->title, 'category' => $definition->category->value, 'retryable' => $definition->retryable, 'detail_keys' => $definition->detailKeys];
    }
    expect($fixture['schema'])->toBe('corex-public-error-contract/2')
        ->and($actual)->toBe($fixture['definitions'])
        ->and($scanner->diagnostics($root.'/packages', $registry))->toBe([]);
    foreach ($fixture['dynamic_adapters'] as $adapter) {
        expect(hash_file('sha256', $root.'/'.$adapter['source']))->toBe($adapter['sha256']);
    }
    foreach ($fixture['contracts'] as $row) {
        $details = array_fill_keys(array_filter($row['detail_keys'], static fn (string $key): bool => $key !== '*'), 'safe');
        $registry->assertOccurrence(new ErrorDescriptor($registry->fromWire($row['code'])->code, ErrorCategory::from($row['category']), $row['retryable'], $row['path'], $details));
    }
});

test('ErrorContract AST gate rejects literals through resolved names', function (string $source): void {
    $root = sys_get_temp_dir().'/corex-error-literal-'.bin2hex(random_bytes(6));
    mkdir($root);
    $file = $root.'/Probe.php';
    file_put_contents($file, '<?php '.$source);

    try {
        $scanner = new ErrorSourceScanner;
        $diagnostics = $scanner->diagnostics($root, $scanner->catalogue(SourceScanner::repoRoot()));
        expect($diagnostics)->toHaveCount(1)->and($diagnostics[0])->toStartWith('ERROR-LITERAL ');
    } finally {
        unlink($file);
        rmdir($root);
    }
})->with([
    'alias' => "use CoreX\\Operations\\Errors\\Exceptions\\ApiError as Failure; new Failure('bad', 409, 'probe');",
    'group import' => "use CoreX\\Operations\\Errors\\Exceptions\\{ApiError as Failure}; new Failure(errorCode: 'bad', status: 409, message: 'probe');",
    'FQCN' => "new \\CoreX\\Operations\\Errors\\Exceptions\\ApiError('bad', 409, 'probe');",
    'descriptor' => "use CoreX\\Errors\\Data\\ErrorDescriptor as Descriptor; new Descriptor(code: 'bad', category: \\CoreX\\Errors\\Enums\\ErrorCategory::Internal);",
]);
