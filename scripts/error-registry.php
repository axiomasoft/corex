#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Errors\Internal;

use RuntimeException;
use Throwable;

require dirname(__DIR__).'/vendor/autoload.php';

try {
    if (array_diff(array_slice($argv, 1), ['--check', '--report']) !== []) {
        throw new RuntimeException('Expected --check or --report.');
    }
    $root = dirname(__DIR__);
    $scanner = new ErrorSourceScanner;
    $registry = $scanner->catalogue($root);
    $diagnostics = $scanner->diagnostics($root.'/packages', $registry);
    $definitions = [];
    foreach ($registry->all() as $definition) {
        $definitions[] = [
            'code' => $definition->code->value(),
            'enum' => $definition->code::class,
            'type' => $definition->type,
            'status' => $definition->defaultStatus,
            'title' => $definition->title,
            'category' => $definition->category->value,
            'retryable' => $definition->retryable,
            'detail_keys' => $definition->detailKeys,
        ];
    }
    echo json_encode(['schema' => 'corex-error-registry/1', 'definitions' => $definitions, 'diagnostics' => $diagnostics], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    exit($diagnostics === [] ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(2);
}
