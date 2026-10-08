<?php

declare(strict_types=1);

require getcwd().'/vendor/autoload.php';
use Opis\JsonSchema\Validator;

$schema = json_decode('{"type":"object","required":["id","secret"],"properties":{"id":{"type":"string"},"secret":{"type":"string"}},"additionalProperties":false}', false, 512, JSON_THROW_ON_ERROR);
$internalData = (object) ['id' => 'order-1', 'secret' => 'hidden'];
$externalData = (object) ['id' => 'order-1'];
$validator = new Validator;
$schemaUri = 'https://schemas.example.test/orders.placed/v1.json';
echo json_encode([
    'scenario' => 'D90 permits a MessageContract dataSchema for payload; existing ExportProjector removes secret',
    'internal_dataschema' => $schemaUri,
    'external_dataschema_by_D90' => $schemaUri,
    'internal_data_valid' => $validator->validate($internalData, $schema)->isValid(),
    'external_data_valid' => $validator->validate($externalData, $schema)->isValid(),
], JSON_THROW_ON_ERROR)."\n";
