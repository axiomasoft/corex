<?php

declare(strict_types=1);

use Composer\Package\Locker;

require 'vendor/autoload.php';

// Provider staging changes only repository transport and restores the exact
// manifests after resolution. Bind each portable lock to that restored input
// using Composer's own hashing algorithm; package entries remain unchanged.
$manifests = ['composer.json', ...glob('packages/*/composer.json'), ...glob('products/*/composer.json')];
$changed = 0;
foreach ($manifests as $manifest) {
    $lockPath = dirname($manifest).'/composer.lock';

    if (! is_file($lockPath)) {
        continue;
    }
    $source = file_get_contents($lockPath);
    $hash = Locker::getContentHash(file_get_contents($manifest));
    $projected = preg_replace('/("content-hash"\s*:\s*")[a-f0-9]{32}(")/', '${1}'.$hash.'${2}', $source, limit: 1, count: $count);

    if ($count !== 1) {
        throw new RuntimeException('Missing lock content hash: '.$lockPath);
    }

    if ($projected !== $source) {
        file_put_contents($lockPath, $projected);
        $changed++;
    }
}
echo 'portable lock hashes: '.$changed." restored\n";
