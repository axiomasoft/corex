<?php

declare(strict_types=1);

namespace CoreX\Search\Tests\Unit;

use Closure;
use CoreX\CoreServiceProvider;
use CoreX\Data\Contracts\FieldUsePolicy;
use CoreX\Data\Contracts\FieldUseSnapshot;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\FieldUsePlan;
use CoreX\Data\Data\FieldUseRequest;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Search\Errors\Exceptions\IndexNotReady;
use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Errors\Exceptions\SearchMetadataDenied;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Query\Contracts\SearchMetadata;
use CoreX\Search\SearchServiceProvider;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SearchMetadataReaderTest extends TestCase
{
    private QueryFixture $fixture;

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, SearchServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixture = new QueryFixture;
        $this->fixture->bind($this->app);
        $this->app->instance(FieldUsePolicy::class, new class($this->fixture) implements FieldUseSnapshot
        {
            private ?QueryFixture $snapshot = null;

            public function __construct(private readonly QueryFixture $fixture) {}

            public function plan(DataScope $scope, EntityHandle $entity, FieldUseRequest $request): FieldUsePlan
            {
                return ($this->snapshot ?? $this->fixture)->plan($scope, $entity, $request);
            }

            public function inSnapshot(DataScope $scope, EntityHandle $entity, Closure $read): mixed
            {
                $this->snapshot = clone $this->fixture;

                try {
                    return $read();
                } finally {
                    $this->snapshot = null;
                }
            }
        });
    }

    public function test_backend_canary_cannot_release_an_earlier_field_grant(): void
    {
        $fixture = $this->fixture;
        $this->canary(static fn (): bool => true);
        $before = $this->app->make(SearchMetadata::class)->describe($fixture->scope, $fixture->entity);
        self::assertSame(['name', 'paid'], array_column(JsonDocuments::map($before->body)['fields'], 'id'));

        $this->canary(static function () use ($fixture): bool {
            $fixture->deniedUses = ['read|name', 'search|name', 'filter|name', 'sort|name'];

            return true;
        });
        $after = $this->app->make(SearchMetadata::class)->describe($fixture->scope, $fixture->entity);
        self::assertSame(['paid'], array_column(JsonDocuments::map($after->body)['fields'], 'id'));
        self::assertNotSame($before->witness, $after->witness);
    }

    public function test_backend_canary_cannot_release_an_earlier_policy_epoch(): void
    {
        $fixture = $this->fixture;
        $this->canary(static fn (): bool => true);
        $before = $this->app->make(SearchMetadata::class)->describe($fixture->scope, $fixture->entity);
        $this->canary(static function () use ($fixture): bool {
            $fixture->policyEpoch++;

            return true;
        });
        $after = $this->app->make(SearchMetadata::class)->describe($fixture->scope, $fixture->entity);
        self::assertSame($before->body->canonicalJson, $after->body->canonicalJson);
        self::assertNotSame($before->witness, $after->witness);
    }

    #[DataProvider('releaseChanges')]
    public function test_backend_canary_checks_current_release_conditions(string $change, string $failure): void
    {
        $fixture = $this->fixture;
        $this->canary(static function () use ($fixture, $change): bool {
            match ($change) {
                'entity-read' => $fixture->deniedUses = ['read|'],
                'empty-corpus' => $fixture->corpora['read|'] = [],
                'unrepresentable-corpus' => $fixture->corpora['read|'] = ['a'],
                'projection' => $fixture->revision++,
                'generation' => $fixture->generation++,
            };

            return true;
        });
        $fixture->allowCorpus = false;
        $this->expectException($failure);
        $this->app->make(SearchMetadata::class)->describe($fixture->scope, $fixture->entity);
    }

    public static function releaseChanges(): array
    {
        return [
            ['entity-read', SearchMetadataDenied::class],
            ['empty-corpus', SearchMetadataDenied::class],
            ['unrepresentable-corpus', SearchFeatureUnavailable::class],
            ['projection', IndexNotReady::class],
            ['generation', IndexNotReady::class],
        ];
    }

    public function test_a_policy_without_a_release_snapshot_cannot_advertise_metadata(): void
    {
        $fixture = $this->fixture;
        $this->app->instance(FieldUsePolicy::class, $fixture);
        $index = $this->createMock(IndexDriver::class);
        $index->expects(self::never())->method('canary');
        $this->app->instance(IndexDriver::class, $index);

        $this->expectException(SearchMetadataDenied::class);
        $this->app->make(SearchMetadata::class)->describe($fixture->scope, $fixture->entity);
    }

    private function canary(Closure $observe): void
    {
        $index = $this->createStub(IndexDriver::class);
        $index->method('canary')->willReturnCallback($observe);
        $this->app->instance(IndexDriver::class, $index);
    }
}
