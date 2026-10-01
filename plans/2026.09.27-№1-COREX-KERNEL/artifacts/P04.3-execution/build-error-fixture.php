<?php

use CoreX\Errors\Internal\ErrorSourceScanner;

require getcwd().'/vendor/autoload.php';
$fixture = require 'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P04.3-execution/preimage/public-error-contract.php';
$registry = (new ErrorSourceScanner)->catalogue(getcwd());
$rows = [];
foreach ($registry->all() as $definition) {
    $rows[] = ['code' => $definition->code->value(), 'enum' => $definition->code::class, 'type' => $definition->type, 'status' => $definition->defaultStatus, 'title' => $definition->title, 'category' => $definition->category->value, 'retryable' => $definition->retryable, 'detail_keys' => $definition->detailKeys];
}
$fixture['schema'] = 'corex-public-error-contract/2';
$fixture['definitions'] = $rows;
foreach ($fixture['contracts'] as &$row) {
    $code = $row['code'];
    $owner = str_ends_with($row['owner'], 'custom-fields') ? 'custom-fields' : 'operations';

    if (str_starts_with($code, 'webhook_') || $code === 'unknown_message_contract') {
        $owner = 'webhooks';
    }

    if ($code === 'internal_error') {
        $owner = 'core';
    }
    $row['code'] = $owner.'.'.$code;
    $row['owner'] = $owner === 'core' ? 'axiomasoft/corex' : 'axiomasoft/corex-'.$owner;
    $row['declaration'] = 'registered-enum';
}
unset($row);
$fixture['dynamic_adapters'][0]['owner'] = 'axiomasoft/corex-webhooks';
foreach ($fixture['dynamic_adapters'] as &$row) {
    $row['sha256'] = hash_file('sha256', $row['source']);
}
file_put_contents('tests/fixtures/public-error-contract.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn ".var_export($fixture, true).";\n");
