<?php

declare(strict_types=1);

use CoreX\Search\Meilisearch\Internal\MeilisearchIndexAdapter;
use CoreX\Search\Support\IndexBatch;
use CoreX\Search\Support\IndexSwap;
use Meilisearch\Client;

it('returns an asynchronous task receipt for index submission', function (): void {
    $client = new Client('http://127.0.0.1:7700');
    $adapter = new MeilisearchIndexAdapter(client: $client, indexPrefix: 'corex-p67');

    $submission = $adapter->submit(new IndexBatch(generation: 'g1', documents: [['id' => 'machine-1', 'title' => 'Hydraulic pump']]));
    $client->waitForTask((int) $submission->taskId);
    $outcome = $adapter->status($submission);

    expect($submission->generation)->toBe('g1')
        ->and($submission->taskId)->not->toBe('')
        ->and($outcome->status)->toBe('succeeded');
})->group('meilisearch');

it('returns an asynchronous task receipt for an index swap', function (): void {
    $client = new Client('http://127.0.0.1:7700');
    $adapter = new MeilisearchIndexAdapter(client: $client, indexPrefix: 'corex-p67-swap');
    $first = $adapter->submit(new IndexBatch(generation: 'old', documents: [['id' => 'machine-1', 'title' => 'Old pump']]));
    $second = $adapter->submit(new IndexBatch(generation: 'new', documents: [['id' => 'machine-1', 'title' => 'New pump']]));
    $client->waitForTask((int) $first->taskId);
    $client->waitForTask((int) $second->taskId);

    $submission = $adapter->swap(new IndexSwap(fromGeneration: 'old', toGeneration: 'new'));
    $client->waitForTask((int) $submission->taskId);

    expect($submission->generation)->toBe('new')
        ->and($adapter->status($submission)->status)->toBe('succeeded');
})->group('meilisearch');
