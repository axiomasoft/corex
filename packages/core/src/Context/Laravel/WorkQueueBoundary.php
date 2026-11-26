<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use Closure;
use CoreX\Context\Contracts\CentralWorkRunner;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Context\Internal\WorkProcessHealth;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use Illuminate\Container\Container;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Log\Context\Repository as LogContext;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\JobName;
use Illuminate\Support\Facades\Context;
use Packman\Attributes\Internal;
use Throwable;

#[Internal]
final class WorkQueueBoundary
{
    /** @var list<array{job: Job, handler: string, transport: string, connection: string, scope: ?DataScope, proof: ?StoredWorkProof, context: array<string,mixed>, hidden: array<string,mixed>, outer: array<string,mixed>, outerHidden: array<string,mixed>}> */
    private array $frames = [];

    /** @param Closure():Container $application */
    public function __construct(private readonly WorkQueueHandlers $handlers, private readonly Closure $application) {}

    /** @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function stamp(array $payload): array
    {
        $transport = $payload['job'] ?? null;
        $id = $payload['uuid'] ?? null;
        $data = $payload['data'] ?? null;

        if (! is_string($transport) || ! is_string($id)) {
            throw new ScopeContextMismatch('Invalid work transport.');
        }
        $handler = $transport;

        if ($transport === CallQueuedHandler::class.'@call') {
            if (! is_array($data) || ! is_object($object = $data['command'] ?? null)) {
                throw new ScopeContextMismatch('Missing work command.');
            }
            $handler = $object::class;
            $data['commandName'] = $handler;
            $data['command'] = serialize(clone $object);
        }
        $mode = $this->handlers->mode($handler);
        $wire = null;
        $seal = null;

        if ($mode === 'tenant') {
            $scope = ($this->application)()->make(DataScopeFactory::class)->forCurrentPrincipal(($this->application)()->make(TenantContextResolver::class)->current());
            $wire = ($this->application)()->make(DataScopeCodec::class)->encode($scope);
            $seal = ($this->application)()->make(StoredWorkIssuer::class)->issue($scope, 'corex.queue', $this->commandRef(id: $id, handler: $handler, data: $data))->seal;
        }
        $log = ($this->application)()->make(LogContext::class);
        $metadata = ['seal' => $seal, 'context' => array_filter($log->all(), is_scalar(...)), 'hidden' => array_filter($log->allHidden(), is_scalar(...)), 'handler' => $handler, 'transport' => $transport, 'mode' => $mode, 'scope' => $wire];

        return ['job' => WorkQueueHandler::class.'@fire', 'illuminate:log:context' => null, 'corex_scope' => [...$metadata, 'signature' => $this->sign(id: $id, metadata: $metadata, data: $data)]];
    }

    public function processing(JobProcessing $event): void
    {
        ($this->application)()->make(WorkProcessHealth::class)->check();
        $job = $event->job;
        $payload = $job->payload();

        try {
            $metadata = $payload['corex_scope'] ?? null;

            if (($payload['illuminate:log:context'] ?? null) !== null || ($payload['job'] ?? null) !== WorkQueueHandler::class.'@fire' || ! is_array($metadata)
                || ! is_array($metadata['context'] ?? null) || ! is_array($metadata['hidden'] ?? null) || ! is_string($metadata['signature'] ?? null) || ! is_string($metadata['handler'] ?? null)
                || ! is_string($metadata['transport'] ?? null) || ! array_key_exists('scope', $metadata)
                || ! is_string($payload['uuid'] ?? null) || $this->handlers->mode($metadata['handler']) !== ($metadata['mode'] ?? null)) {
                throw new ScopeContextMismatch('Undeclared or malformed work envelope.');
            }
            $data = $payload['data'];

            if ($metadata['transport'] === CallQueuedHandler::class.'@call') {
                if (! is_array($data) || ($data['commandName'] ?? null) !== $metadata['handler'] || ! is_string($data['command'] ?? null)) {
                    throw new ScopeContextMismatch('Job command differs from its work handler.');
                }

                if (! str_starts_with($data['command'], 'O:')) {
                    $data['command'] = ($this->application)()->make(Encrypter::class)->decrypt($data['command']);
                }
            } elseif ($metadata['transport'] !== $metadata['handler']) {
                throw new ScopeContextMismatch('Job transport differs from its work handler.');
            }
            $signature = $metadata['signature'];
            unset($metadata['signature']);

            if (! hash_equals($this->sign(id: $payload['uuid'], metadata: $metadata, data: $data), $signature)) {
                throw new ScopeContextMismatch('Unverified job scope.');
            }

            if (($metadata['mode'] === 'central') !== ($metadata['scope'] === null)) {
                throw new ScopeContextMismatch('Job type does not match its scope.');
            }
            $wire = $metadata['scope'];

            if ($wire !== null && (! is_array($wire) || count(array_filter(array_keys($wire), is_string(...))) !== count($wire))) {
                throw new ScopeContextMismatch('Malformed work scope.');
            }
            $scope = $wire === null ? null : ($this->application)()->make(DataScopeCodec::class)->decode(array_filter($wire, is_string(...), ARRAY_FILTER_USE_KEY));

            if ($scope !== null && ! is_string($metadata['seal'] ?? null)) {
                throw new ScopeContextMismatch('Missing work provenance.');
            }
            $proof = $scope === null ? null : new StoredWorkProof(owner: 'corex.queue', reference: $this->commandRef(id: $payload['uuid'], handler: $metadata['handler'], data: $data), seal: $metadata['seal']);
            $log = ($this->application)()->make(LogContext::class);
            $this->frames[] = ['proof' => $proof, 'outer' => $log->all(), 'outerHidden' => $log->allHidden(), 'context' => array_filter($metadata['context'], is_string(...), ARRAY_FILTER_USE_KEY), 'hidden' => array_filter($metadata['hidden'], is_string(...), ARRAY_FILTER_USE_KEY), 'job' => $job, 'handler' => $metadata['handler'], 'transport' => $metadata['transport'], 'connection' => $event->connectionName, 'scope' => $scope];
        } catch (Throwable $error) {
            // Refused bytes must never enter Laravel's serialized failure callback.
            $this->quarantine(job: $job, connection: $event->connectionName, error: $error);

            throw $error;
        }
    }

    public function finished(JobAttempted $event): void
    {
        $key = array_key_last($this->frames);

        if ($key !== null && $this->frames[$key]['job'] === $event->job) {
            $frame = array_pop($this->frames);
            Context::clearResolvedInstance(LogContext::class);
            ($this->application)()->make(LogContext::class)->flush()->add($frame['outer'])->addHidden($frame['outerHidden']);
        }
    }

    public function invoke(Job $job, Closure $callback): mixed
    {
        $key = array_key_last($this->frames);

        if ($key === null || $this->frames[$key]['job'] !== $job) {
            throw new ScopeContextMismatch('No admitted job attempt.');
        }
        $frame = $this->frames[$key];
        $entered = false;
        $work = function () use ($frame, $callback, &$entered): mixed {
            $entered = true;
            Context::clearResolvedInstance(LogContext::class);
            ($this->application)()->make(LogContext::class)->flush()->add($frame['context'])->addHidden($frame['hidden']);
            [$class, $method] = JobName::parse($frame['transport']);

            if (! is_string($class) || ! is_string($method)) {
                throw new ScopeContextMismatch('Invalid declared work handler.');
            }

            return $callback(($this->application)()->make($class), $method);
        };

        try {
            return $frame['scope'] === null
                ? ($this->application)()->make(CentralWorkRunner::class)->run($work)
                : ($this->application)()->make(StoredWorkRunner::class)->runStored($frame['scope'], $frame['proof'] ?? throw new ScopeContextMismatch('Missing work provenance.'), $work);
        } catch (ScopeContextMismatch|ScopeStale $refusal) {
            if (! $entered && ! $job->hasFailed()) {
                $this->quarantine(job: $job, connection: $frame['connection'], error: $refusal);
            }

            throw $refusal;
        }
    }

    private function quarantine(Job $job, string $connection, Throwable $error): void
    {
        $job->markAsFailed();
        ($this->application)()->make(Dispatcher::class)->dispatch(new JobFailed($connection, $job, $error));
        $job->delete();
    }

    private function commandRef(string $id, string $handler, mixed $data): string
    {
        return hash('sha256', serialize([$id, $handler, $data]));
    }

    /** @param array<array-key,mixed> $metadata */
    private function sign(string $id, array $metadata, mixed $data): string
    {
        $key = ($this->application)()->make(Encrypter::class)->getKey();

        return hash_hmac('sha256', json_encode([$id, $metadata, hash('sha256', serialize($data))], JSON_THROW_ON_ERROR), $key);
    }
}
