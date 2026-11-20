<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Search\Index\Data\IndexTarget;
use CoreX\SearchMeilisearch\Contracts\MeilisearchCredentialProvider;
use CoreX\SearchMeilisearch\Enums\MeilisearchAccess;
use Packman\Config\Data\SecretRef;
use Ramsey\Uuid\Uuid;
use RuntimeException;

final class SearchScenarioCredentials implements MeilisearchCredentialProvider
{
    public function forTarget(IndexTarget $target, MeilisearchAccess $access): SecretRef
    {
        // The disposable host uses its provisioning credential only on the keys endpoint.
        SearchScenarioMeili::register($target);
        $admin = SearchScenarioMeili::admin();
        $uid = Uuid::uuid5(Uuid::NAMESPACE_URL, 'urn:corex:scenario-meili:'.$target->uid.':'.$access->value)->toString();
        $name = 'CoreX scenario '.$target->cluster.' '.$access->value;
        $actions = match ($access) {
            MeilisearchAccess::Search => ['search'],
            MeilisearchAccess::Index => ['indexes.create', 'indexes.delete', 'settings.update', 'documents.add', 'documents.delete'],
            MeilisearchAccess::Observe => ['indexes.get', 'settings.get', 'tasks.get'],
        };
        $existing = $admin->get('/keys/'.$uid);

        if ($existing->status() === 404) {
            $created = $admin->post('/keys', ['uid' => $uid, 'name' => $name, 'actions' => $actions, 'indexes' => [$target->uid], 'expiresAt' => null]);
            $existing = $created->status() === 409 ? $admin->get('/keys/'.$uid) : $created;
        }
        $row = $existing->throw()->json();

        if ($row['uid'] !== $uid || $row['name'] !== $name || $row['indexes'] !== [$target->uid]
            || $row['actions'] !== $actions || $row['expiresAt'] !== null || ! is_string($row['key']) || $row['key'] === '') {
            throw new RuntimeException('The disposable credential does not prove its exact index and allowed actions.');
        }

        return new SecretRef($row['key']);
    }
}
