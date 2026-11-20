<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Search\Index\Data\IndexTarget;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class SearchScenarioMeili
{
    public static function document(IndexTarget $target, string $id): ?array
    {
        self::assertOwned($target);
        $result = self::admin()->get('/indexes/'.$target->uid.'/documents/'.$target->documentId(SearchScenarioData::record($id)));

        return $result->status() === 404 ? null : $result->throw()->json();
    }

    public static function task(string $receipt): array
    {
        $task = explode('.', $receipt, 2)[0];

        if (preg_match('/\A[0-9]+\z/D', $task) !== 1) {
            throw new RuntimeException('The installed adapter returned no real task identifier.');
        }

        return FieldPgWitness::await('the actual Meilisearch task is terminal', static function () use ($task): ?array {
            $result = self::admin()->get('/tasks/'.$task)->throw()->json();

            return in_array($result['status'], ['enqueued', 'processing'], true) ? null : $result;
        });
    }

    public static function value(?array $document, string $field): mixed
    {
        return $document['f_'.bin2hex($field)] ?? null;
    }

    public static function assertOwned(IndexTarget $target): void
    {
        if ($target->cluster !== getenv('COREX_SCENARIO_MEILI_NAMESPACE') || $target->backend !== 'meilisearch'
            || $target->uid !== IndexTarget::uidFor($target->scope, $target->entity, $target->backend, $target->cluster, $target->generation, $target->allocationId)) {
            throw new RuntimeException('Invalid disposable index UID.');
        }
    }

    public static function register(IndexTarget $target): void
    {
        self::assertOwned($target);
        $scope = $target->scope;
        $parts = ['corex.search.uid.v2', $scope->deploymentId, $scope->bindingId, (string) $scope->bindingGeneration,
            $scope->restoreEpoch, $scope->accountId, $scope->accountIncarnation, $scope->workspaceId === null ? 'account' : 'workspace',
            $scope->workspaceId ?? '', $target->entity->value, $target->backend, $target->cluster, (string) $target->generation, $target->allocationId];
        $entry = json_encode(['uid' => $target->uid, 'parts' => $parts], JSON_THROW_ON_ERROR)."\n";

        if (file_put_contents(getenv('COREX_SCENARIO_BARRIERS').'/meilisearch-targets.ndjson', $entry, FILE_APPEND | LOCK_EX) !== strlen($entry)) {
            throw new RuntimeException('Could not retain the disposable index coordinate.');
        }
    }

    public static function admin(): PendingRequest
    {
        $key = getenv('MEILISEARCH_MASTER_KEY');
        $client = Http::baseUrl((string) getenv('MEILISEARCH_HOST'))->acceptJson()->timeout(3);

        return is_string($key) && $key !== '' ? $client->withToken($key) : $client;
    }
}
