<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Values\Data;

use CoreX\CustomFields\Schemas\Data\SchemaRevision;
use CoreX\Data\Data\RecordRef;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldWriteReceipt
{
    public function __construct(public RecordRef $record, public SchemaRevision $schemaRevision, public DocumentRevision $valueRevision, public string $messageId, public string $operationKey, public string $receiptId) {}
}
