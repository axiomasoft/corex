<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Contracts\IndexLookupAccess;
use CoreX\Search\Index\Data\IndexBatch;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Internal\SearchOptions;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\SearchServiceProvider;
use CoreX\SearchMeilisearch\Contracts\MeilisearchCredentialProvider;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use CoreX\SearchMeilisearch\Exceptions\InvalidMeilisearchConfiguration;
use CoreX\SearchMeilisearch\Internal\MeilisearchCandidateDriver;
use CoreX\SearchMeilisearch\Internal\MeilisearchIndexAdapter;
use CoreX\SearchMeilisearch\Internal\MeilisearchOptions;
use CoreX\SearchMeilisearch\SearchMeilisearchServiceProvider;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Orchestra\Testbench\TestCase;
use Packman\Config\Data\SecretRef;

final class SearchMeilisearchServiceProviderTest extends TestCase
{
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [SearchServiceProvider::class, SearchMeilisearchServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TenantContextResolver::class, new class implements TenantContextResolver
        {
            public function current(): TenantContext
            {
                return new TenantContext(new AccountRef('meili-test', 'meili-test', 'active', [], []));
            }
        });
        CallerWorkScopeFixture::bind($this->app);
    }

    public function test_meilisearch_driver_without_consumer_configuration_throws(): void
    {
        $this->app['config']->set('corex-search.driver', 'meilisearch');
        $this->app->forgetInstance(SearchOptions::class);

        expect(fn (): CandidateDriver => $this->app->make(CandidateDriver::class))
            ->toThrow(InvalidMeilisearchConfiguration::class);
    }

    public function test_meilisearch_driver_resolves_adapter_for_explicit_consumer_configuration(): void
    {
        $this->app['config']->set('corex-search.driver', 'meilisearch');
        $this->app->forgetInstance(SearchOptions::class);
        $this->app['config']->set('corex-search-meilisearch.host', 'http://127.0.0.1:7700');
        $this->configureScopedCredentialProvider();
        $this->app->forgetInstance(MeilisearchOptions::class);

        expect($this->app->make(CandidateDriver::class))->toBeInstanceOf(MeilisearchCandidateDriver::class)
            ->and($this->app->make(IndexDriver::class))->toBeInstanceOf(MeilisearchIndexAdapter::class);
    }

    public function test_meilisearch_driver_resolves_regardless_of_provider_registration_order(): void
    {
        $app = $this->app;
        new SearchMeilisearchServiceProvider($app)->register();
        new SearchServiceProvider($app)->register();
        new SearchMeilisearchServiceProvider($app)->boot();

        $app['config']->set('corex-search.driver', 'meilisearch');
        $app->forgetInstance(SearchOptions::class);
        $app['config']->set('corex-search-meilisearch.host', 'http://127.0.0.1:7700');
        $this->configureScopedCredentialProvider();
        $app->forgetInstance(MeilisearchOptions::class);

        expect($app->make(CandidateDriver::class))->toBeInstanceOf(MeilisearchCandidateDriver::class);
    }

    public function test_meilisearch_rejects_a_configured_host_without_scoped_credentials(): void
    {
        $this->app['config']->set('corex-search.driver', 'meilisearch');
        $this->app['config']->set('corex-search-meilisearch.host', 'http://127.0.0.1:7700');
        $this->app->forgetInstance(SearchOptions::class);
        $this->app->forgetInstance(MeilisearchOptions::class);

        expect(fn (): CandidateDriver => $this->app->make(CandidateDriver::class))
            ->toThrow(InvalidMeilisearchConfiguration::class, 'A scoped Meilisearch credential provider is required');
    }

    private function configureScopedCredentialProvider(): void
    {
        $this->app->instance(MeilisearchCredentialProvider::class, new class implements MeilisearchCredentialProvider
        {
            public function forTarget(IndexTarget $target, MeilisearchAccess $access): SecretRef
            {
                throw new LogicException('Driver construction must not load provider credentials.');
            }
        });
        $this->app->instance(IndexLookupAccess::class, new class implements IndexLookupAccess
        {
            public function assert(IndexBatch $batch): void
            {
                throw new LogicException('Driver construction must not inspect provider receipts.');
            }
        });
    }
}
