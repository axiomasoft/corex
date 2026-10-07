<?php

declare(strict_types=1);

use CoreX\Search\Data\IndexBatch;
use CoreX\Search\Data\IndexSwap;
use CoreX\SearchMeilisearch\Internal\MeilisearchIndexAdapter;
use CoreX\SearchMeilisearch\Tests\MeilisearchTestCase;

uses(MeilisearchTestCase::class)->group('meilisearch');

it('returns an asynchronous task receipt for index submission', function (): void {
    $index = $this->index('g1');
    $adapter = new MeilisearchIndexAdapter(client: $this->client, indexPrefix: $this->indexPrefix);

    $submission = $adapter->submit(new IndexBatch(generation: 'g1', documents: [['id' => 'machine-1', 'title' => 'Hydraulic pump']]));
    $this->client->waitForTask((int) $submission->taskId);
    $outcome = $adapter->status($submission);

    expect($submission->generation)->toBe('g1')
        ->and($submission->taskId)->not->toBe('')
        ->and($outcome->status)->toBe('succeeded')
        ->and($index->getDocument('machine-1'))->toBe(['id' => 'machine-1', 'title' => 'Hydraulic pump']);
})->group('meilisearch');

it('returns an asynchronous task receipt for an index swap', function (): void {
    $oldIndex = $this->index('old');
    $newIndex = $this->index('new');
    $adapter = new MeilisearchIndexAdapter(client: $this->client, indexPrefix: $this->indexPrefix);
    $first = $adapter->submit(new IndexBatch(generation: 'old', documents: [['id' => 'machine-1', 'title' => 'Old pump']]));
    $second = $adapter->submit(new IndexBatch(generation: 'new', documents: [['id' => 'machine-1', 'title' => 'New pump']]));
    $this->client->waitForTask((int) $first->taskId);
    $this->client->waitForTask((int) $second->taskId);

    $submission = $adapter->swap(new IndexSwap(fromGeneration: 'old', toGeneration: 'new'));
    $this->client->waitForTask((int) $submission->taskId);

    expect($submission->generation)->toBe('new')
        ->and($adapter->status($submission)->status)->toBe('succeeded')
        ->and($oldIndex->getDocument('machine-1')['title'])->toBe('New pump')
        ->and($newIndex->getDocument('machine-1')['title'])->toBe('Old pump');
})->group('meilisearch');
