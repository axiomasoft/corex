<?php

declare(strict_types=1);

namespace CoreX\Foundation\Data;

use CoreX\Data\Data\DataScope;
use JsonSerializable;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class LifecycleBlocker implements JsonSerializable
{
    public function __construct(public string $owner, public string $resourceKind, public string $resourceId, public DataScope $scope, public string $requiredAction) {}

    /** @return array{owner:string,resource_kind:string,resource_id:string,scope:DataScope,required_action:string} */
    public function jsonSerialize(): array
    {
        return ['owner' => $this->owner, 'resource_kind' => $this->resourceKind, 'resource_id' => $this->resourceId, 'scope' => $this->scope, 'required_action' => $this->requiredAction];
    }
}
