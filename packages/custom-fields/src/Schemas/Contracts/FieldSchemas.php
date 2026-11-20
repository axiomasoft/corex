<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Contracts;

use CoreX\Context\Data\OperationKey;
use CoreX\CustomFields\Schemas\Data\ClaimGeneration;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Schemas\Data\PlanDigest;
use CoreX\CustomFields\Schemas\Data\SchemaOperationRef;
use CoreX\CustomFields\Schemas\Data\SchemaOperationStatus;
use CoreX\CustomFields\Schemas\Data\SchemaPlan;
use CoreX\CustomFields\Schemas\Data\SchemaPlanRef;
use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface FieldSchemas
{
    public function plan(DataScope $scope, FieldSchemaChange $change, SchemaRevision $expected): SchemaPlan;

    public function apply(DataScope $scope, SchemaPlanRef $plan, PlanDigest $expected, OperationKey $key): SchemaOperationRef;

    public function inspect(DataScope $scope, SchemaOperationRef $operation): SchemaOperationStatus;

    public function retry(DataScope $scope, SchemaOperationRef $operation, ClaimGeneration $expected): SchemaOperationStatus;

    public function cancel(DataScope $scope, SchemaOperationRef $operation, ClaimGeneration $expected): SchemaOperationStatus;
}
