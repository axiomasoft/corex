<?php

declare(strict_types=1);

namespace App\Scenario;

use Closure;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\OperationKey;
use CoreX\Data\Data\DataScope;
use CoreX\Messaging\Contracts\BrokerReceiver;
use CoreX\Messaging\Contracts\CloudEventCodec;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Messaging\Contracts\OutboxRelay;
use CoreX\Messaging\Data\ConsumerId;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Search\Index\Contracts\IndexWorker;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Lane\Contracts\SearchInvalidations;
use CoreX\Search\Rebuild\Contracts\RebuildWorker;
use CoreX\Search\Rebuild\Contracts\SearchRebuilds;
use CoreX\Search\Rebuild\Data\RebuildInput;
use CoreX\Search\Rebuild\Data\RebuildRef;
use CoreX\Search\Rebuild\Data\RebuildStatus;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class SearchScenarioRebuild
{
    public static function start(DataScope $scope, string $key): RebuildStatus
    {
        $rebuilds = resolve(SearchRebuilds::class);
        $plan = $rebuilds->plan($scope, new RebuildInput(SearchScenarioData::entity()));
        $ref = $rebuilds->start($scope, $plan->ref, new OperationKey($key, 1));

        return $rebuilds->inspect($scope, $ref);
    }

    public static function bootstrap(DataScope $scope, string $key): RebuildStatus
    {
        $start = self::start($scope, $key);
        self::scan($scope, $start);
        $barrier = resolve(RebuildWorker::class)->barrier($scope, $start->ref);
        self::confirmed($scope, $barrier->destination, $barrier->barrier ?? throw new RuntimeException('No publish barrier.'));
        $published = resolve(RebuildWorker::class)->publish($scope, $start->ref);

        if ($published->state !== 'published') {
            throw new RuntimeException('The installed owner failed to publish initial search generation: '.$published->state);
        }

        return $published;
    }

    public static function scan(DataScope $scope, RebuildStatus $start): RebuildStatus
    {
        return SearchScenarioMeili::await('the stable-keyset index scan catches up for rebuild '.$start->ref->id, static function () use ($scope, $start): ?RebuildStatus {
            $state = resolve(SearchRebuilds::class)->inspect($scope, $start->ref);

            if ($state->state === 'catching-up') {
                return $state;
            }

            if ($state->state !== 'scanning') {
                throw new RuntimeException('The index scan for rebuild '.$start->ref->id.' reached '.$state->state.' before catch-up.');
            }
            self::step($scope, $start->destination);

            return null;
        });
    }

    public static function step(DataScope $scope, int $generation): bool
    {
        return resolve(IndexWorker::class)->advance($scope, SearchScenarioData::entity(), $generation);
    }

    public static function confirmed(DataScope $scope, int $generation, int $sequence): object
    {
        $db = Scenario::connection('a');

        return SearchScenarioMeili::await('generation '.$generation.' confirms the native Meilisearch dirty prefix through '.$sequence, static function () use ($scope, $generation, $sequence, $db): ?object {
            $row = self::generation($db, $generation, $scope->workspaceId ?? throw new RuntimeException('An index fixture requires a workspace.'), $scope->restoreEpoch);

            if ((int) $row->confirmed_seq >= $sequence) {
                return $row;
            }

            if (in_array($row->state, ['quarantined', 'retired', 'dropped'], true)) {
                throw new RuntimeException('Meilisearch UID '.$row->uid.' reached '.$row->state.' at sequence '.$row->confirmed_seq.'.');
            }
            self::step($scope, $generation);

            return null;
        });
    }

    public static function generation(Connection $db, int $generation, string $workspace = Scenario::W1, ?string $epoch = null): object
    {
        return $db->table('corex_search_generations')->where([
            'lane_key' => self::lane($db, $workspace, $epoch)->lane_key, 'generation' => $generation,
        ])->firstOrFail();
    }

    public static function lane(Connection $db, string $workspace = Scenario::W1, ?string $epoch = null): object
    {
        $query = $db->table('corex_search_lanes')->selectRaw('*, xmin::text AS tuple_version')->where([
            'entity' => SearchScenarioRecord::ENTITY, 'workspace_scope' => 'workspace:'.$workspace,
            'cluster' => getenv('COREX_SCENARIO_MEILI_NAMESPACE'), 'backend' => 'meilisearch',
        ]);

        if ($epoch !== null) {
            $query->where('restore_epoch', $epoch);
        }

        return $query->sole();
    }

    public static function target(DataScope $scope, int $generation): IndexTarget
    {
        $row = self::generation(Scenario::connection('a'), $generation, $scope->workspaceId ?? throw new RuntimeException('An index fixture requires a workspace.'), $scope->restoreEpoch);

        return new IndexTarget($scope, SearchScenarioData::entity(), 'meilisearch', $row->cluster, $generation, $row->uid, $row->mapping_digest, $row->allocation_id);
    }

    public static function inspect(DataScope $scope, string $id): RebuildStatus
    {
        return resolve(SearchRebuilds::class)->inspect($scope, new RebuildRef($id));
    }

    public static function change(DataScope $scope, string $id, Closure $mutation): array
    {
        $messageId = (string) Str::uuid7();
        $owner = DB::connection();
        resolve(WorkScopeRunner::class)->run($scope, static function () use ($scope, $id, $mutation, $messageId, $owner): void {
            $owner->transaction(static function () use ($scope, $id, $mutation, $messageId, $owner): void {
                $mutation($owner);
                resolve(Outbox::class)->append($owner, new MessageEnvelope(
                    messageId: $messageId, source: 'urn:scenario:search-owner', name: 'scenario.search.changed', contractVersion: 2,
                    occurredAt: resolve(ScenarioClock::class)->now(), scope: $scope, payload: MessagePayload::empty(),
                    piiClass: PiiClass::None, subject: SearchScenarioData::record($id), correlationId: $messageId,
                ));
            });
        });
        $relay = resolve(OutboxRelay::class);
        $claims = $relay->lease((string) Str::uuid7(), 10);
        $claim = array_find($claims, static fn ($item): bool => $item->envelope->messageId === $messageId);

        if ($claim === null) {
            throw new RuntimeException('The index owner change did not enter the real outbox.');
        }
        $relay->assertClaim($claim);
        Scenario::connection('control')->table('scenario_search_broker')->insert([
            'id' => $messageId, 'wire' => resolve(CloudEventCodec::class)->encode($claim->envelope),
        ]);
        $consumer = new ConsumerId('scenario.search-index', 1);
        $broker = resolve(BrokerReceiver::class);
        $apply = static function (ConnectionInterface $writer, MessageEnvelope $event) use ($id): void {
            resolve(SearchInvalidations::class)->apply($writer, $event, SearchScenarioData::entity(), [SearchScenarioData::record($id)]);
        };
        $receipt = $broker->consume($consumer, $messageId, $apply);
        $duplicate = $broker->consume($consumer, $messageId, $apply);
        $relay->markPublished($claim);

        return ['id' => $messageId, 'receipt' => $receipt->value, 'duplicate' => $duplicate->value];
    }
}
