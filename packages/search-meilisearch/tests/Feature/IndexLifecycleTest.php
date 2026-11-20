<?php

declare(strict_types=1);

use CoreX\Data\Data\RecordRef;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexDocument;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\Search\Index\Enums\IndexOutcome;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use CoreX\SearchMeilisearch\Tests\MeilisearchTestCase;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;
use Meilisearch\Http\Client as HttpClient;

uses(MeilisearchTestCase::class)->group('meilisearch');

it('confirms only the precise provider task and keeps late writes on the immutable old UID', function (): void {
    $context = $this->context();
    $old = $this->target($context);
    $new = $this->target($context, 2);
    $adapter = $this->adapter(new CallerWorkScopeFixture($context, expected: $old->scope));
    $this->provision($adapter, $old);
    $this->provision($adapter, $new);

    $record = new RecordRef('machine', 'opaque/machine-1');
    $oldBatch = new IndexBatch($old, 'upsert', IndexAction::Upsert, [new IndexDocument($record, new ProjectedValues(['title' => 'Old pump']))]);
    $newBatch = new IndexBatch($new, 'upsert', IndexAction::Upsert, [new IndexDocument($record, new ProjectedValues(['title' => 'New pump']))]);
    $newReceipt = $adapter->submit($newBatch);
    $this->await($newReceipt);
    $oldReceipt = $adapter->submit($oldBatch);
    $this->await($oldReceipt);
    [$oldTask, $oldSeal] = explode('.', $oldReceipt->taskId, 2);
    [$newTask] = explode('.', $newReceipt->taskId, 2);
    $observer = $this->scopedClient($old, MeilisearchAccess::Observe);
    $forged = new IndexReceipt($newTask.'.'.$oldSeal);
    $changed = new IndexBatch($old, 'changed', IndexAction::Upsert, $oldBatch->documents);
    $title = 'f_'.bin2hex('title');

    expect($oldTask)->not->toBe($newTask)
        ->and($adapter->canary($old))->toBeTrue()
        ->and($adapter->canary($new))->toBeTrue()
        ->and($adapter->lookup($oldBatch, $oldReceipt))->toBe(IndexOutcome::Confirmed)
        ->and($adapter->lookup($newBatch, $newReceipt))->toBe(IndexOutcome::Confirmed)
        ->and($adapter->lookup($oldBatch, null))->toBe(IndexOutcome::Unknown)
        ->and($adapter->lookup($oldBatch, $newReceipt))->toBe(IndexOutcome::Unknown)
        ->and($adapter->lookup($oldBatch, $forged))->toBe(IndexOutcome::Unknown)
        ->and($adapter->lookup($changed, $oldReceipt))->toBe(IndexOutcome::Unknown)
        ->and($observer->getTask((int) $oldTask)['uid'])->toBe((int) $oldTask)
        ->and(fn () => $observer->getTask((int) $newTask))->toThrow(ApiException::class)
        ->and(fn () => $observer->index($old->uid)->addDocuments([['id' => 'forbidden']], 'id'))->toThrow(ApiException::class)
        ->and($this->client->index($old->uid)->getDocument($old->documentId($record))[$title])->toBe('Old pump')
        ->and($this->client->index($new->uid)->getDocument($new->documentId($record))[$title])->toBe('New pump');

    $delete = new IndexBatch($old, 'delete', IndexAction::DeleteRecords, [new IndexDocument($record, new ProjectedValues([]))]);
    $tombstone = $adapter->submit($delete);
    $this->await($tombstone);
    expect($adapter->lookup($delete, $tombstone))->toBe(IndexOutcome::Confirmed)
        ->and(fn () => $this->client->index($old->uid)->getDocument($old->documentId($record)))->toThrow(ApiException::class)
        ->and($this->client->index($new->uid)->getDocument($new->documentId($record))[$title])->toBe('New pump');

    $drop = new IndexBatch($old, 'drop', IndexAction::Drop, []);
    $dropped = $adapter->submit($drop);
    $this->await($dropped);
    expect($adapter->lookup($drop, $dropped))->toBe(IndexOutcome::Confirmed)
        ->and($adapter->canary($old))->toBeFalse()
        ->and($adapter->canary($new))->toBeTrue();
});

it('leaves an accepted submission unknown when the Meilisearch acknowledgment is lost', function (): void {
    $context = $this->context();
    $target = $this->target($context);
    $current = new CallerWorkScopeFixture($context, expected: $target->scope);
    $this->provision($this->adapter($current), $target);
    $key = $this->scopedKey($target, MeilisearchAccess::Index);
    $transport = new class($this->endpoint, $key) extends HttpClient
    {
        public ?int $observedTask = null;

        public function post(string $path, $body = null, array $query = [], ?string $contentType = null): never
        {
            $accepted = parent::post($path, $body, $query, $contentType);
            $this->observedTask = $accepted['taskUid'];

            throw new RuntimeException('The provider acknowledgment was lost.');
        }
    };
    $client = new class($this->endpoint, $key, $transport) extends Client
    {
        public function __construct(string $url, ?string $key, private readonly HttpClient $transport)
        {
            parent::__construct($url, $key);
        }

        public function index(string $uid): Indexes
        {
            return new Indexes($this->transport, $uid);
        }
    };
    $adapter = $this->adapter($current, fn (IndexTarget $t, MeilisearchAccess $mode): Client => $mode === MeilisearchAccess::Index
        ? $client
        : $this->scopedClient($t, $mode));
    $record = new RecordRef('machine', 'lost-ack');
    $batch = new IndexBatch($target, 'lost-ack', IndexAction::Upsert, [new IndexDocument($record, new ProjectedValues(['title' => 'Committed pump']))]);

    expect(fn () => $adapter->submit($batch))->toThrow(RuntimeException::class, 'The provider acknowledgment was lost.')
        ->and($transport->observedTask)->toBeInt();
    $task = $this->client->waitForTask($transport->observedTask);

    expect($task['status'])->toBe('succeeded')
        ->and($task['indexUid'])->toBe($target->uid)
        ->and($this->client->index($target->uid)->getDocument($target->documentId($record))['f_'.bin2hex('title')])->toBe('Committed pump')
        ->and($adapter->lookup($batch, null))->toBe(IndexOutcome::Unknown);
});
