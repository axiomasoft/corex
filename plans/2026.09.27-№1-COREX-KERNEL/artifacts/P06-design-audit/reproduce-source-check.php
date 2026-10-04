<?php

// Literal normative source check from research/P06-design.md section 3.3 step 2.
$pattern = '~\A[A-Za-z0-9\-._\~:/?#\[\]@!$&\'()*+,;=%]+\z~';
foreach (['axiomasoft/corex-custom-fields', 'has space', '%', '%zz', '1:foo', 'http://[not-ip]/'] as $source) {
    echo json_encode(['source' => $source, 'passes_designed_check' => preg_match($pattern, $source) === 1], JSON_UNESCAPED_SLASHES), PHP_EOL;
}
