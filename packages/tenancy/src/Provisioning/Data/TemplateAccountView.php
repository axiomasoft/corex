<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class TemplateAccountView
{
    public function __construct(public int $templateVersion, public string $clusterId, public string $databaseName) {}
}
