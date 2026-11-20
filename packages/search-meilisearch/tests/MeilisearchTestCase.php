<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Tests;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Data\Data\EntityHandle;
use CoreX\Search\Index\Contracts\IndexLookupAccess;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexReceipt;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Index\Enums\IndexAction;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use CoreX\SearchMeilisearch\Internal\MeilisearchIndexAdapter;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Meilisearch\Client;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class MeilisearchTestCase extends TestCase
{
    protected Client $client;

    protected string $endpoint;

    /** @var list<string> */
    private array $indexUids = [];

    /** @var array<string, array{uid:string,key:string}> */
    private array $keys = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $endpoint = getenv('MEILISEARCH_HOST') ?: 'http://127.0.0.1:7700';

        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'http' || ! in_array(parse_url($endpoint, PHP_URL_HOST), ['127.0.0.1', 'localhost', '[::1]'], true)) {
            throw new RuntimeException('Meilisearch feature tests require an isolated loopback service.');
        }
        $this->endpoint = $endpoint;
        $key = getenv('MEILISEARCH_MASTER_KEY') ?: throw new RuntimeException('A keyed disposable Meilisearch service is required.');
        $this->client = new Client($this->endpoint, $key);
    }

    protected function target(TenantContext $context, int $generation = 1): IndexTarget
    {
        $scope = CallerWorkScopeFixture::forContext($context, 'operator', str_repeat('e', 64));
        $entity = new EntityHandle('machine');
        $allocationId = bin2hex(random_bytes(16));
        $uid = IndexTarget::uidFor($scope, $entity, 'meilisearch', 'cluster-test', $generation, $allocationId);
        $this->indexUids[] = $uid;

        return new IndexTarget($scope, $entity, 'meilisearch', 'cluster-test', $generation, $uid, str_repeat('b', 64), $allocationId);
    }

    protected function context(string $workspace = 'space-a'): TenantContext
    {
        return new TenantContext(new AccountRef('account', 'account', 'active', [], []),
            new WorkspaceRef($workspace, $workspace, 'project', $workspace, null));
    }

    protected function provision(MeilisearchIndexAdapter $adapter, IndexTarget $target): void
    {
        $batch = new IndexBatch($target, 'create-'.$target->generation, IndexAction::Create, []);
        $receipt = $adapter->submit($batch);
        $this->await($receipt);
    }

    /** @param ?Closure(IndexTarget, MeilisearchAccess): Client $clients */
    protected function adapter(CurrentWorkScope $current, ?Closure $clients = null): MeilisearchIndexAdapter
    {
        $lookup = new readonly class($current) implements IndexLookupAccess
        {
            public function __construct(private CurrentWorkScope $current) {}

            public function assert(IndexBatch $batch): void
            {
                $this->current->assert($batch->target->scope);
            }
        };

        return new MeilisearchIndexAdapter($clients ?? $this->scopedClient(...), $current, 'cluster-test', $lookup);
    }

    protected function scopedClient(IndexTarget $target, MeilisearchAccess $access): Client
    {
        return new Client($this->endpoint, $this->scopedKey($target, $access));
    }

    protected function scopedKey(IndexTarget $target, MeilisearchAccess $access): string
    {
        $coordinate = $target->uid.'/'.$access->value;

        if (! isset($this->keys[$coordinate])) {
            $actions = match ($access) {
                MeilisearchAccess::Search => ['search'],
                MeilisearchAccess::Index => ['indexes.create', 'indexes.delete', 'settings.update', 'documents.add', 'documents.delete'],
                MeilisearchAccess::Observe => ['indexes.get', 'settings.get', 'tasks.get'],
            };
            $providerKey = $this->client->createKey(['actions' => $actions, 'indexes' => [$target->uid], 'expiresAt' => null]);
            $uid = $providerKey->getUid();
            $key = $providerKey->getKey();

            if ($uid === null || $key === null || $providerKey->getActions() !== $actions || $providerKey->getIndexes() !== [$target->uid]) {
                throw new RuntimeException('The local Meilisearch service did not issue an exact scoped credential.');
            }
            $this->keys[$coordinate] = ['uid' => $uid, 'key' => $key];
        }

        return $this->keys[$coordinate]['key'];
    }

    protected function await(IndexReceipt $receipt): void
    {
        $task = $this->client->waitForTask((int) explode('.', $receipt->taskId, 2)[0]);
        self::assertSame('succeeded', $task['status']);
    }

    #[Override]
    protected function tearDown(): void
    {
        try {
            foreach (array_unique($this->indexUids) as $uid) {
                $task = $this->client->deleteIndex($uid);
                $this->client->waitForTask($task['taskUid']);
            }
        } finally {
            try {
                foreach ($this->keys as $credential) {
                    $this->client->deleteKey($credential['uid']);
                }
            } finally {
                parent::tearDown();
            }
        }
    }
}
