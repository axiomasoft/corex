<?php

declare(strict_types=1);

namespace CoreX\Search\Tests\Unit;

use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\CoreServiceProvider;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Exceptions\InvalidSearchConfiguration;
use CoreX\Search\Internal\SearchOptions;
use CoreX\Search\Internal\UnavailableSearchProvider;
use CoreX\Search\Projection\Contracts\SearchProjectionContributor;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Query\Contracts\CandidateDriver;
use CoreX\Search\Query\Contracts\Search;
use CoreX\Search\Query\Data\SearchQuery;
use CoreX\Search\SearchServiceProvider;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;

final class SearchServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, SearchServiceProvider::class];
    }

    public function test_boot_and_null_driver_are_lazy(): void
    {
        self::assertInstanceOf(Search::class, $this->app->make(Search::class));
        self::assertInstanceOf(UnavailableSearchProvider::class, $this->app->make(CandidateDriver::class));
        $f = new QueryFixture;
        $f->bind($this->app);
        $this->app['config']->set('corex-search.driver', 'null');
        $this->app->forgetInstance(SearchOptions::class);
        $this->expectException(SearchFeatureUnavailable::class);
        $this->app->make(Search::class)->query($f->scope, new SearchQuery($f->entity));
    }

    public function test_weak_cursor_secret_fails_before_driver_factory(): void
    {
        $f = new QueryFixture;
        $f->bind($this->app);
        $this->app['config']->set('corex-search.cursor_secret', 'short');
        $this->app->forgetInstance(SearchOptions::class);

        try {
            $this->app->make(Search::class)->query($f->scope, new SearchQuery($f->entity));
            self::fail();
        } catch (InvalidSearchConfiguration) {
            self::assertSame(0, $f->factoryCalls);
        }
    }

    public function test_missing_adapter_is_diagnosed_in_verified_work(): void
    {
        $f = new QueryFixture;
        $f->bind($this->app);
        $this->app['config']->set('corex-search.driver', 'missing');
        $this->app->forgetInstance(SearchOptions::class);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(Search::class)->query($f->scope, new SearchQuery($f->entity));
    }

    public function test_factory_is_called_after_scope_verification_and_never_cached(): void
    {
        $f = new QueryFixture;
        $f->bind($this->app);
        $search = $this->app->make(Search::class);
        $query = new SearchQuery($f->entity);

        try {
            $search->query(CallerWorkScopeFixture::scope('other', null, 'other', str_repeat('b', 64)), $query);
            self::fail();
        } catch (ScopeContextMismatch) {
            self::assertSame(0, $f->factoryCalls);
        }
        $search->query($f->scope, $query);
        $search->query($f->scope, $query);
        self::assertSame(2, $f->factoryCalls);
    }

    public function test_tagged_projection_derives_revision_and_rejects_duplicate_owners(): void
    {
        $f = new QueryFixture;
        $this->app->instance(QueryFixture::class, $f);
        $this->app->tag(QueryFixture::class, SearchProjectionContributor::class);
        $context = new ProjectionContext($f->scope, $f->entity);
        $part = $this->app->make(SearchProjectionContributor::class);
        $first = $part->describe($context)->revision;
        $f->revision++;
        self::assertNotSame($first, $part->describe($context)->revision);
        $this->app->tag(QueryFixture::class, SearchProjectionContributor::class);
        $this->expectException(InvalidArgumentException::class);
        $this->app->make(SearchProjectionContributor::class)->describe($context);
    }
}
