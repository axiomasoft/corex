<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Laravel;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\OperationResourceAuthorizer;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Webhooks\Contracts\WebhookSubscriptionStore;
use CoreX\Webhooks\Data\WebhookSubscription;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Internal\WebhookManagement;
use CoreX\Webhooks\Internal\WebhookReplay;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class WebhookOperation implements OperationHandler, OperationResourceAuthorizer
{
    public function __construct(
        private DataScopeFactory $scopes,
        private WorkScopeRunner $runner,
        private WebhookSubscriptionStore $subscriptions,
        private WebhookManagement $management,
        private WebhookReplay $replay,
        private WebhookTopicCatalogue $topics,
        private Gate $gate,
        private ClockInterface $clock,
    ) {}

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        if (! $context instanceof ClientRequestContext) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'The invocation scope is not authorized.');
        }
        $scope = $this->scope($context);
        $operation = $context->metadata()->operationId;
        $id = $parameters['id'] ?? '';
        $client = $context->clientId();
        $reason = $this->text(input: $input, key: 'audit_reason');
        $cursor = $this->text(input: $input, key: 'cursor');

        try {
            return $this->runner->run(scope: $scope, work: fn (): array => match ($operation) {
                'webhook-subscriptions.list' => ['items' => $this->management->subscriptions($scope)],
                'webhook-subscriptions.create' => $this->create(scope: $scope, context: $context, input: $input),
                'webhook-subscriptions.update' => $this->update(scope: $scope, context: $context, id: $id, input: $input),
                'webhook-subscriptions.disable' => $this->project($this->subscriptions->disable(
                    scope: $scope, id: $id, expectedRevision: $this->integer(input: $input, key: 'expected_revision'),
                )),
                'webhook-deliveries.list' => $this->deliveries(scope: $scope, client: $client, input: $input),
                'webhook-deliveries.show' => $this->management->delivery(scope: $scope, id: $id)
                    ?? throw new ApiError(errorCode: OperationsErrorCode::ResourceNotFound, status: 404, message: 'Webhook delivery does not exist.'),
                'webhook-deliveries.attempts' => ['items' => $this->management->attempts(scope: $scope, id: $id)],
                'webhook-deliveries.replay' => $this->replay->one(scope: $scope, deliveryId: $id, explicit: true, auditReason: $reason),
                'webhook-deliveries.range-replay' => $this->replay->range(scope: $scope, clientId: $client,
                    subscriptionId: $this->text(input: $input, key: 'subscription_id'), from: new DateTimeImmutable($this->text(input: $input, key: 'from')),
                    to: new DateTimeImmutable($this->text(input: $input, key: 'to')), max: $this->integer(input: $input, key: 'max'),
                    cursor: $cursor === '' ? null : $cursor, auditReason: $reason),
                default => throw new ApiError(errorCode: OperationsErrorCode::OperationNotFound, status: 404, message: 'Operation not found.'),
            });
        } catch (InvalidArgumentException) {
            throw new ApiError(errorCode: OperationsErrorCode::InvalidRequest, status: 422, message: 'Webhook input is invalid.');
        }
    }

    public function authorize(OperationInvocation $invocation, ?StoredOperationOutcome $outcome = null): void
    {
        $id = $invocation->operation->id;
        $context = $invocation->context;
        $scope = $this->scope($context);
        $target = $invocation->parameters['id'] ?? null;
        $input = JsonDocuments::map(value: $invocation->input);

        $this->runner->run(scope: $scope, work: function () use ($context, $scope, $id, $target, $input, $outcome): void {
            if ($id === 'webhook-subscriptions.create') {
                $topic = $this->topics->get(name: $this->text(input: $input, key: 'topic'), version: $this->integer(input: $input, key: 'topic_version'));
                $this->admitTopic(context: $context, scope: $scope, topic: $topic);
            } elseif ($id === 'webhook-subscriptions.update' || $id === 'webhook-subscriptions.disable') {
                $subscription = $this->subscriptions->find(scope: $scope, id: $target ?? '');

                if ($subscription === null || $subscription->major !== 'v2') {
                    throw $this->missing();
                }

                if ($id === 'webhook-subscriptions.update') {
                    $topic = $this->topics->get(name: $subscription->topic, version: $subscription->topicVersion);
                    $this->admitTopic(context: $context, scope: $scope, topic: $topic);
                }
            } elseif ($target !== null && $this->management->delivery(scope: $scope, id: $target) === null) {
                throw $this->missing();
            } elseif ($id === 'webhook-deliveries.range-replay' && $outcome?->kind === 'success') {
                $selected = JsonDocuments::optionalMap(value: $outcome->data)['selected'] ?? [];
                foreach (is_array($selected) ? $selected : [] as $delivery) {
                    if (! is_string($delivery) || $this->management->delivery(scope: $scope, id: $delivery) === null) {
                        throw $this->missing();
                    }
                }
            }
        });
    }

    private function scope(RequestContext $context): DataScope
    {
        $scope = $this->scopes->forCurrentPrincipal($context->tenant());
        $principal = $context->principal();

        if (! $context instanceof ClientRequestContext || $scope->principalId !== $principal->principalId()
            || $scope->principalKind !== $principal->principalType()->value
            || (str_starts_with($context->metadata()->operationId, 'webhook-subscriptions.') && $scope->workspaceId !== null)) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'The invocation scope is not authorized.');
        }

        return $scope;
    }

    private function admitTopic(RequestContext $context, DataScope $scope, WebhookTopic $topic): void
    {
        if ($topic->major !== 'v2') {
            throw $this->missing();
        }

        $sensitive = $this->gate->forUser($context->principal())->allows('corex.webhooks.subscribe-sensitive', [$scope, $topic]);
        $this->topics->admit(topic: $topic, sensitiveAbility: $sensitive, policyAllows: $sensitive);
    }

    private function missing(): ApiError
    {
        return new ApiError(errorCode: OperationsErrorCode::ResourceNotFound, status: 404, message: 'Webhook resource does not exist.');
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function create(DataScope $scope, RequestContext $context, array $input): array
    {
        $topic = $this->topics->get(name: $this->text(input: $input, key: 'topic'), version: $this->integer(input: $input, key: 'topic_version'));

        if ($topic->major !== 'v2') {
            throw new ApiError(errorCode: OperationsErrorCode::UnknownMessageContract, status: 422, message: 'Webhook topic is unavailable.');
        }
        $sensitive = $this->gate->forUser($context->principal())->allows('corex.webhooks.subscribe-sensitive', [$scope, $topic]);
        $now = $this->clock->now();

        return $this->project($this->subscriptions->create(
            scope: $scope,
            subscription: new WebhookSubscription(id: (string) Str::uuid(), revision: 0, major: 'v2',
                topic: $topic->name, topicVersion: $topic->version, endpointUrl: $this->text(input: $input, key: 'endpoint_url'),
                status: 'active', maxPiiClass: $topic->piiClass, keyRefs: $this->keys($input), createdAt: $now, updatedAt: $now),
            sensitiveAbility: $sensitive, policyAllows: $sensitive,
        ));
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function update(DataScope $scope, RequestContext $context, string $id, array $input): array
    {
        $subscription = $this->subscriptions->find(scope: $scope, id: $id)
            ?? throw new ApiError(errorCode: OperationsErrorCode::ResourceNotFound, status: 404, message: 'Webhook subscription does not exist.');
        $topic = $this->topics->get(name: $subscription->topic, version: $subscription->topicVersion);
        $sensitive = $this->gate->forUser($context->principal())->allows('corex.webhooks.subscribe-sensitive', [$scope, $topic]);

        return $this->project($this->subscriptions->update(scope: $scope, id: $id,
            expectedRevision: $this->integer(input: $input, key: 'expected_revision'), endpointUrl: $this->text(input: $input, key: 'endpoint_url'),
            keyRefs: $this->keys($input), sensitiveAbility: $sensitive, policyAllows: $sensitive));
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    private function deliveries(DataScope $scope, string $client, array $input): array
    {
        $filters = [];

        if (is_string($input['status'] ?? null)) {
            $filters['status'] = $input['status'];
        }

        if (is_string($input['subscription_id'] ?? null)) {
            $filters['subscription_id'] = $input['subscription_id'];
        }
        $cursor = $input['cursor'] ?? null;

        return $this->management->deliveries(scope: $scope, clientId: $client, filters: $filters,
            cursor: is_string($cursor) ? $cursor : null, limit: $this->integer(input: $input, key: 'limit', default: 50));
    }

    /** @return array<string,mixed> */
    private function project(WebhookSubscription $subscription): array
    {
        return ['id' => $subscription->id, 'revision' => $subscription->revision, 'topic' => $subscription->topic,
            'topic_version' => $subscription->topicVersion, 'status' => $subscription->status,
            'key_ref_count' => count($subscription->keyRefs)];
    }

    /** @param array<string,mixed> $input */
    private function text(array $input, string $key): string
    {
        $value = $input[$key] ?? null;

        return is_string($value) ? $value : '';
    }

    /** @param array<string,mixed> $input */
    private function integer(array $input, string $key, int $default = 0): int
    {
        $value = $input[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<string,mixed>  $input
     * @return list<string>
     */
    private function keys(array $input): array
    {
        $refs = $input['key_refs'] ?? [];

        return is_array($refs) ? array_values(array_filter($refs, is_string(...))) : [];
    }
}
