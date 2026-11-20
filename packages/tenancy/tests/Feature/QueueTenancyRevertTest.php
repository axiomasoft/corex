<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Event;

uses()->group('pg');

it('quarantines an unstamped framework job before Laravel resolves its handler', function (): void {
    $manager = app(TenancyManager::class);
    $job = new SyncJob(app(), json_encode(['uuid' => 'wrong', 'job' => 'untrusted@fire', 'data' => []], JSON_THROW_ON_ERROR), 'sync', 'default');
    expect(fn () => Event::dispatch(new JobProcessing('sync', $job)))->toThrow(ScopeContextMismatch::class);
    Event::dispatch(new JobAttempted('sync', $job));
    expect($job->isDeleted())->toBeTrue()->and($job->hasFailed())->toBeTrue()->and($manager->context())->toBeNull();
});
