<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Internal;

use CoreX\Search\Contracts\IndexDriver;
use CoreX\Search\Data\IndexBatch;
use CoreX\Search\Data\IndexOutcome;
use CoreX\Search\Data\IndexSubmission;
use CoreX\Search\Data\IndexSwap;
use InvalidArgumentException;
use Meilisearch\Client;

final readonly class MeilisearchIndexAdapter implements IndexDriver
{
    public function __construct(private Client $client, private string $indexPrefix)
    {
        if (preg_match('/\A[a-zA-Z0-9_-]{1,180}\z/D', $indexPrefix) !== 1) {
            throw new InvalidArgumentException('Meilisearch index prefix is invalid.');
        }
    }

    public function submit(IndexBatch $batch): IndexSubmission
    {
        $this->assertGeneration($batch->generation);
        $task = Value::map($this->client->index($this->indexUid($batch->generation))->addDocuments($batch->documents, 'id'));

        return new IndexSubmission(taskId: Value::text($task['taskUid']), generation: $batch->generation);
    }

    public function status(IndexSubmission $submission): IndexOutcome
    {
        $task = $this->client->index($this->indexUid($submission->generation))->getTask((int) $submission->taskId);
        $status = $task['status'] ?? null;

        if (! is_string($status) || ! in_array($status, ['enqueued', 'processing', 'succeeded', 'failed', 'canceled'], true)) {
            return new IndexOutcome(status: 'unknown', errorCode: 'task-status-unknown');
        }

        $error = $task['error'] ?? null;

        return new IndexOutcome(status: $status, errorCode: is_array($error) && is_string($error['code'] ?? null) ? $error['code'] : null);
    }

    public function swap(IndexSwap $swap): IndexSubmission
    {
        $this->assertGeneration($swap->fromGeneration);
        $this->assertGeneration($swap->toGeneration);
        $task = $this->client->swapIndexes([[$this->indexUid($swap->fromGeneration), $this->indexUid($swap->toGeneration)]]);

        return new IndexSubmission(taskId: Value::text($task['taskUid']), generation: $swap->toGeneration);
    }

    private function assertGeneration(string $generation): void
    {
        if (preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/D', $generation) !== 1) {
            throw new InvalidArgumentException('Index generation is invalid.');
        }
    }

    private function indexUid(string $generation): string
    {
        return $this->indexPrefix.'_'.$generation;
    }
}
