<?php

declare(strict_types=1);

use CoreX\Media\Exceptions\MediaOperationConflict;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Internal\Jobs\PurgeMedia;
use CoreX\Media\Internal\MediaOperationStore;
use Illuminate\Support\Facades\DB;
use Vaulter\Media\Contracts\Cas\BlobPins;
use Vaulter\Media\Contracts\Cas\BlobStore;

it('deduplicates bytes while retaining independent owner pins through purge', function (): void {
    allowMedia();
    $library = resolve(MediaLibrary::class);
    $first = $library->store(mediaContext(), upload('shared-first', 'same bytes'));
    $second = $library->store(mediaContext(), upload('shared-second', 'same bytes'));
    $blob = DB::table('med_files')->where('id', $first->id)->value('blob_id');

    expect(DB::table('med_files')->where('id', $second->id)->value('blob_id'))->toBe($blob)
        ->and(resolve(BlobPins::class)->countByOwner())->toBe(['corex.media.file' => 2]);

    $library->delete(mediaContext(), $first->id);
    new PurgeMedia($first->id)->handle(resolve(MediaOperationStore::class));
    expect(resolve(BlobPins::class)->countByOwner())->toBe(['corex.media.file' => 1]);
    $stream = ($library->read(mediaContext(), $second->id)->openStream)();
    expect(stream_get_contents($stream))->toBe('same bytes');
    fclose($stream);

    $library->delete(mediaContext(), $second->id);
    new PurgeMedia($second->id)->handle(resolve(MediaOperationStore::class));
    expect(resolve(BlobPins::class)->countByOwner())->toBe([]);
    // Physical deletion is exclusively Vaulter GC's responsibility, including its grace period.
    $stream = resolve(BlobStore::class)->read($blob);
    expect(is_resource($stream))->toBeTrue();
    fclose($stream);
});

it('rolls back a media reference when its atomic Vaulter pin cannot be acquired', function (): void {
    allowMedia();
    $realPins = resolve(BlobPins::class);
    $pins = Mockery::mock(BlobPins::class);
    $pins->shouldReceive('pin')->once()->andThrow(new RuntimeException('pin refused'));
    app()->instance(BlobPins::class, $pins);
    app()->forgetScopedInstances();

    expect(fn () => resolve(MediaLibrary::class)->store(mediaContext(), upload('pin-failed', 'bytes')))
        ->toThrow(RuntimeException::class, 'pin refused');
    expect(DB::table('med_files')->where('request_key', 'pin-failed')->exists())->toBeFalse()
        ->and($realPins->countByOwner())->toBe([]);
});

it('rejects retained media services after the active connection changes', function (): void {
    allowMedia();
    $library = resolve(MediaLibrary::class);
    $file = $library->store(mediaContext(), upload('connection-change', 'bytes'));
    $read = $library->read(mediaContext(), $file->id);
    config(['database.connections.other' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
    DB::setDefaultConnection('other');

    expect(fn () => $library->store(mediaContext(), upload('wrong-connection', 'bytes')))->toThrow(MediaOperationConflict::class)
        ->and(fn () => ($read->openStream)())->toThrow(MediaOperationConflict::class)
        ->and(fn () => $library->delete(mediaContext(), $file->id))->toThrow(MediaOperationConflict::class);
});
