<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Runtime\Internal;

use Closure;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Packman\Attributes\Internal;

/**
 * Per-job tenant context. `processing` enters the account named by the job payload
 * (`corex_tenant`); `finished` — the single completion point, dispatched from the `finally`
 * of both Worker::process and SyncQueue::executeJob — ends it. Frames are a LIFO stack keyed
 * by the job object, so a sync job (also a nested one) restores its caller's account and
 * workspace, while an async job always starts and ends central. A job whose account is
 * missing fails instead of running on the previous database.
 */
#[Internal]
final class QueueTenantContext
{
    /** @var list<array{job: Job, previous: ?TenantContext}> */
    private array $frames = [];

    /** @param Closure(string): ?WorkspaceRef $workspaceResolver tenant query, called only after initialize */
    public function __construct(
        private readonly TenancyManager $manager,
        private readonly AccountRead $accounts,
        private readonly Closure $workspaceResolver,
        private readonly string $centralConnection,
    ) {}

    public function processing(JobProcessing $event): void
    {
        $restore = $event->job instanceof SyncJob || $this->frames !== [];
        $previous = $restore ? $this->manager->context() : null;
        $this->frames[] = ['job' => $event->job, 'previous' => $previous]; // before end/find/initialize
        $this->manager->end();

        $payload = $event->job->payload()['corex_tenant'] ?? null;

        if ($payload === null) {
            return; // central job; a sync caller is restored only after this job finished
        }

        if (! is_array($payload) || ! is_string($payload['account'] ?? null)
            || ! array_key_exists('workspace', $payload)
            || ($payload['workspace'] !== null && ! is_string($payload['workspace']))) {
            throw TenantContextUnavailable::invalidPayload();
        }

        $ref = $this->accounts->find(accountId: $payload['account'], connectionName: $this->centralConnection)
            ?? throw TenantContextUnavailable::missingAccount($payload['account']);
        $this->manager->initialize($ref);

        if ($payload['workspace'] !== null) {
            $workspace = ($this->workspaceResolver)($payload['workspace'])
                ?? throw TenantContextUnavailable::missingWorkspace($payload['workspace']);
            $this->manager->setWorkspace($workspace);
        }
    }

    public function finished(JobAttempted $event): void
    {
        $key = array_key_last($this->frames);

        if ($key === null || $this->frames[$key]['job'] !== $event->job) {
            return; // unrelated or repeated completion cannot pop another job's frame
        }

        $frame = $this->frames[$key];
        array_pop($this->frames);
        $this->manager->end();

        if ($frame['previous'] !== null) {
            $this->manager->initialize($frame['previous']->account);
            $this->manager->setWorkspace($frame['previous']->workspace);
        }
    }
}
