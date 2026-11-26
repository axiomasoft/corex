<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Laravel\UploadedFileSource;
use CoreX\Media\Tests\PgTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

uses(PgTestCase::class)->group('pg');

/**
 * Lets a second PostgreSQL connection commit a competing upload at the exact
 * point a store() has passed its replay lookup but not yet inserted: the
 * first clock read of the insert. No timing is involved.
 */
final class RaceBarrierClock implements ClockInterface
{
    public ?Closure $atNextRead = null;

    public function __construct(private readonly ClockInterface $inner) {}

    public function now(): DateTimeImmutable
    {
        $barrier = $this->atNextRead;
        $this->atNextRead = null;

        if ($barrier !== null) {
            $barrier();
        }

        return $this->inner->now();
    }
}

function raceContext(): MediaContext
{
    return new MediaContext(new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'media-race', 'active', [], [])), 'user', 'actor-1');
}

function raceUpload(string $key, string $contents): UploadInput
{
    return new UploadInput($key, UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('race.txt', $contents)), 'attachment');
}

/**
 * Stores a seed file, then arms the clock so that, mid-store, an independent
 * connection commits a copy of the seed row under $key with the given digest
 * and state, as a concurrent uploader of $key would have.
 */
function raceOn(string $key, string $competitorContents, string $competitorState): RaceBarrierClock
{
    app()->instance(MediaPolicy::class, new class implements MediaPolicy
    {
        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return true;
        }
    });
    $clock = new RaceBarrierClock(resolve(ClockInterface::class));
    app()->instance(ClockInterface::class, $clock);
    app()->forgetScopedInstances();
    $seed = resolve(MediaLibrary::class)->store(raceContext(), raceUpload('race-seed', $competitorContents));
    $connection = config('database.connections.pgsql');
    $second = new PDO("pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}", $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $digest = hash('sha256', $key."\0".hash('sha256', $competitorContents)."\0".'attachment');
    $clock->atNextRead = static function () use ($second, $seed, $key, $digest, $competitorState): void {
        $second->prepare(<<<'SQL'
            insert into med_files select (jsonb_populate_record(null::med_files, to_jsonb(f) || jsonb_build_object(
                'id', gen_random_uuid(), 'request_key', ?::text, 'request_digest', ?::text, 'state', ?::text))).*
            from med_files f where f.id = ?
            SQL)->execute([$key, $digest, $competitorState, $seed->id]);
    };

    return $clock;
}

it('returns the concurrent upload when a racing store of the same content loses the insert', function (): void {
    $clock = raceOn('race-same', 'same bytes', 'ready');
    $inserts = 0;
    DB::connection()->beforeExecuting(static function (string $sql) use (&$inserts): void {
        $inserts += str_starts_with($sql, 'insert into "med_files"') ? 1 : 0;
    });

    $file = resolve(MediaLibrary::class)->store(raceContext(), raceUpload('race-same', 'same bytes'));

    expect($clock->atNextRead)->toBeNull()
        ->and($inserts)->toBe(1)
        ->and(DB::table('med_files')->where('request_key', 'race-same')->sole()->id)->toBe($file->id);
});

it('refuses a racing store whose concurrent winner differs or is not ready', function (string $competitorContents, string $competitorState): void {
    raceOn('race-conflict', $competitorContents, $competitorState);

    expect(fn (): mixed => resolve(MediaLibrary::class)->store(raceContext(), raceUpload('race-conflict', 'my bytes')))
        ->toThrow(MediaOperationConflict::class, 'Media request key conflicts with different content or state.')
        ->and(DB::table('med_files')->where('request_key', 'race-conflict')->count())->toBe(1);
})->with([
    'different content' => ['other bytes', 'ready'],
    'winner still staged' => ['my bytes', 'staged'],
]);
