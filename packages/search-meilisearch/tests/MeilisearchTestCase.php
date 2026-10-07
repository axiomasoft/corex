<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Tests;

use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Override;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class MeilisearchTestCase extends TestCase
{
    protected Client $client;

    protected string $indexPrefix;

    /** @var list<string> */
    private array $indexUids = [];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $endpoint = getenv('MEILISEARCH_HOST') ?: 'http://127.0.0.1:7700';

        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'http' || ! in_array(parse_url($endpoint, PHP_URL_HOST), ['127.0.0.1', 'localhost', '[::1]'], true)) {
            throw new RuntimeException('Meilisearch feature tests require an isolated loopback service.');
        }

        $this->client = new Client($endpoint);
        $this->indexPrefix = 'corex-test-'.bin2hex(random_bytes(12));
        $this->indexUids = [];
    }

    protected function index(string $generation): Indexes
    {
        $uid = $this->indexPrefix.'_'.$generation;
        $this->indexUids[] = $uid;

        return $this->client->index($uid);
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
            parent::tearDown();
        }
    }
}
