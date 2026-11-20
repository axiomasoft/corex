<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Laravel;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderReceiptProjector;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Search\Index\Contracts\ActiveGenerations;
use CoreX\Search\Index\Contracts\IndexDriver;
use CoreX\Search\Index\Contracts\IndexLookupAccess;
use CoreX\Search\Index\Contracts\IndexWorker;
use CoreX\Search\Internal\Index\DirtyJournal;
use CoreX\Search\Internal\Index\GenerationWorker;
use CoreX\Search\Internal\Index\IndexProjection;
use CoreX\Search\Internal\Index\IndexReceiptProjector;
use CoreX\Search\Internal\Index\LaneStore;
use CoreX\Search\Internal\Index\RebuildJournal;
use CoreX\Search\Internal\Index\VerifiedIndexLookup;
use CoreX\Search\Internal\SearchOptions;
use CoreX\Search\Lane\Contracts\SearchInvalidations;
use CoreX\Search\Rebuild\Contracts\RebuildWorker;
use CoreX\Search\Rebuild\Contracts\SearchRebuilds;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final class IndexBindings
{
    public static function register(Application $app): void
    {
        $app->scoped(LaneStore::class, static fn (Application $app): LaneStore => new LaneStore(
            $app->make(DatabaseManager::class), $app->make(CurrentWorkScope::class),
            $app->make(SearchOptions::class)->driver,
            $app->make(SearchOptions::class)->cluster));
        $app->alias(LaneStore::class, ActiveGenerations::class);
        $app->scoped(IndexLookupAccess::class, VerifiedIndexLookup::class);
        $app->scoped(SearchInvalidations::class, DirtyJournal::class);
        $app->scoped(IndexWorker::class, static fn (Application $app): GenerationWorker => new GenerationWorker(
            $app->make(LaneStore::class), static fn (): IndexProjection => $app->make(IndexProjection::class), static fn (): IndexDriver => $app->make(IndexDriver::class),
            $app->make(ProviderEffectLedger::class), $app->make(ScopeAdmission::class), $app->make(ClockInterface::class)));
        $app->scoped(RebuildJournal::class, static fn (Application $app): RebuildJournal => new RebuildJournal(
            $app->make(LaneStore::class), static fn (): IndexProjection => $app->make(IndexProjection::class), static fn (): IndexDriver => $app->make(IndexDriver::class),
            $app->make(ClockInterface::class), $app->make(SearchOptions::class)->retentionSeconds));
        $app->alias(RebuildJournal::class, RebuildWorker::class);
        $app->alias(RebuildJournal::class, SearchRebuilds::class);
        $app->tag(IndexReceiptProjector::class, ProviderReceiptProjector::class);

        if ($app->runningInConsole()) {
            $app->make(Dispatcher::class)->listen(ArtisanStarting::class, static fn (ArtisanStarting $event) => $event->artisan->resolveCommands([RunIndexWorker::class]));
        }
    }
}
