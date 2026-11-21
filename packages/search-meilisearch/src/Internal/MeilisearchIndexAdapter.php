<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Contracts\IndexLookupAccess;
use CoreX\Search\Index\Contracts\IndexRemovalEvidence;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Index\Enums\IndexOutcome;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use Exception;
use Meilisearch\Client;
use Meilisearch\Exceptions\ApiException;
use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final readonly class MeilisearchIndexAdapter implements IndexDriver, IndexRemovalEvidence
{
    private const array Settings = [
        'rankingRules' => ['sort'],
        'filterableAttributes' => [MeilisearchNamespace::Stamp, MeilisearchNamespace::Order, 'id'],
        'sortableAttributes' => [MeilisearchNamespace::Order],
    ];

    /** @param Closure(IndexTarget, MeilisearchAccess): Client $clients */
    public function __construct(
        private Closure $clients,
        private CurrentWorkScope $current,
        private string $cluster,
        private IndexLookupAccess $lookupAccess,
    ) {}

    public function submit(IndexBatch $batch): IndexReceipt
    {
        $this->current->assert($batch->target->scope);
        [$documents, $fingerprint] = $this->submission($batch);
        $uid = $batch->target->uid;
        $client = ($this->clients)($batch->target, MeilisearchAccess::Index);
        $index = $client->index($uid);
        $task = match ($batch->action) {
            // Updating settings provisions the fresh UID in one provider task.
            IndexAction::Create => $index->updateSettings(self::Settings),
            IndexAction::Upsert => $index->addDocuments($documents, 'id'),
            IndexAction::DeleteRecords => $index->deleteDocuments(array_column($documents, 'id')),
            IndexAction::Drop => $client->deleteIndex($uid),
        };

        if (! is_array($task)) {
            throw new RuntimeException('Meilisearch did not acknowledge this index submission.');
        }
        $taskId = $task['taskUid'] ?? null;

        if (! is_int($taskId) || $taskId < 0 || ($task['indexUid'] ?? null) !== $uid
            || ($task['type'] ?? null) !== $this->taskType($batch->action)) {
            throw new RuntimeException('Meilisearch did not acknowledge this index submission.');
        }

        return new IndexReceipt($taskId.'.'.hash('sha256', $taskId.'.'.$fingerprint));
    }

    public function lookup(IndexBatch $batch, ?IndexReceipt $receipt): IndexOutcome
    {
        $this->lookupAccess->assert($batch);
        [, $fingerprint] = $this->submission($batch);

        if ($receipt === null || preg_match('/\A(0|[1-9][0-9]{0,18})\.([a-f0-9]{64})\z/', $receipt->taskId, $parts) !== 1
            || ! hash_equals(hash('sha256', $parts[1].'.'.$fingerprint), $parts[2])) {
            return IndexOutcome::Unknown;
        }
        $taskId = filter_var($parts[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if ($taskId === false) {
            return IndexOutcome::Unknown;
        }

        try {
            $task = ($this->clients)($batch->target, MeilisearchAccess::Observe)->getTask($taskId);
        } catch (Exception) {
            return IndexOutcome::Unknown;
        }

        if (($task['uid'] ?? null) !== $taskId || ($task['indexUid'] ?? null) !== $batch->target->uid
            || ($task['type'] ?? null) !== $this->taskType($batch->action)) {
            return IndexOutcome::Unknown;
        }

        return match ($task['status'] ?? null) {
            'enqueued', 'processing' => IndexOutcome::Pending,
            'succeeded' => IndexOutcome::Confirmed,
            'failed', 'canceled' => IndexOutcome::Failed,
            default => IndexOutcome::Unknown,
        };
    }

    public function absent(IndexTarget $target): bool
    {
        $this->current->assert($target->scope);
        MeilisearchNamespace::fingerprint($target, $this->cluster);
        $client = ($this->clients)($target, MeilisearchAccess::Observe);

        try {
            $info = $client->getRawIndex($target->uid);
        } catch (ApiException $error) {
            if ($error->httpStatus === 404 && $error->errorCode === 'index_not_found') {
                return true;
            }

            throw $error;
        }

        if (($info['uid'] ?? null) !== $target->uid) {
            throw SearchFeatureUnavailable::unsupported();
        }

        return false;
    }

    public function canary(IndexTarget $target): bool
    {
        $this->current->assert($target->scope);
        MeilisearchNamespace::fingerprint($target, $this->cluster);

        try {
            $client = ($this->clients)($target, MeilisearchAccess::Observe);
            $info = $client->getRawIndex($target->uid);
            $settings = $client->index($target->uid)->getSettings();
        } catch (Exception) {
            return false;
        }

        return ($info['uid'] ?? null) === $target->uid
            && array_key_exists('primaryKey', $info) && in_array($info['primaryKey'], ['id', null], true)
            && ($settings['rankingRules'] ?? null) === self::Settings['rankingRules']
            && ($settings['filterableAttributes'] ?? null) === self::Settings['filterableAttributes']
            && ($settings['sortableAttributes'] ?? null) === self::Settings['sortableAttributes'];
    }

    /** @return array{list<array<string,bool|int|string|null>>, string} */
    private function submission(IndexBatch $batch): array
    {
        $target = $batch->target;
        $stamp = MeilisearchNamespace::fingerprint($target, $this->cluster);
        $hasDocuments = in_array($batch->action, [IndexAction::Upsert, IndexAction::DeleteRecords], true);

        if ($batch->submissionId === '' || $hasDocuments !== ($batch->documents !== [])) {
            throw SearchFeatureUnavailable::unsupported();
        }
        $documents = [];

        foreach ($batch->documents as $document) {
            if ($document->record->entity !== $target->entity->value) {
                throw SearchFeatureUnavailable::unsupported();
            }
            $id = $target->documentId($document->record);

            if (isset($documents[$id])) {
                throw SearchFeatureUnavailable::unsupported();
            }
            $row = ['id' => $id, MeilisearchNamespace::Stamp => $stamp, MeilisearchNamespace::Order => MeilisearchNamespace::order($document->record->id)];

            if ($batch->action === IndexAction::Upsert) {
                $row[MeilisearchNamespace::WitnessVersion] = 1;
                $values = $document->values->values;
                ksort($values, SORT_STRING);
                foreach ($values as $field => $value) {
                    $row[MeilisearchNamespace::field($field)] = $value;
                    $row[MeilisearchNamespace::witness($field)] = $document->values->fieldWitness($field);
                }
            }
            $documents[$id] = $row;
        }
        $rows = array_values($documents);
        $fingerprint = hash('sha256', json_encode([$stamp, $batch->submissionId, $batch->action->value, $rows], JSON_THROW_ON_ERROR));

        return [$rows, $fingerprint];
    }

    private function taskType(IndexAction $action): string
    {
        return match ($action) {
            IndexAction::Create => 'settingsUpdate',
            IndexAction::Upsert => 'documentAdditionOrUpdate',
            IndexAction::DeleteRecords => 'documentDeletion',
            IndexAction::Drop => 'indexDeletion',
        };
    }
}
