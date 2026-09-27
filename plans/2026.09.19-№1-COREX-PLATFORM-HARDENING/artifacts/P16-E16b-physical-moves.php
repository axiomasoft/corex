<?php

declare(strict_types=1);

/**
 * Physical layout moves for P16.4–P16.6 (FQCN-stable via composer PSR-4 arrays).
 *
 * @usage php plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/artifacts/P16-E16b-physical-moves.php [p164|p165|p166|all]
 */
$root = dirname(__DIR__, 3);
$stage = $argv[1] ?? 'all';
$pkg = 'packages/operations';

/** @return list<array{0: string, 1: string}> */
function p164Moves(string $pkg): array
{
    $map = [
        'Definition' => [
            'src/Operation.php',
            'src/OperationRegistry.php',
            'src/Contracts/OperationHandler.php',
            'src/Contracts/SchemaContributor.php',
            'src/Data/ResourceId.php',
            'src/Enums/OperationMode.php',
            'src/Internal/DeclaredSchemaContributor.php',
            'src/Internal/OperationContractHasher.php',
            'src/Internal/OperationFingerprint.php',
        ],
        'Invocation' => [
            'src/OperationDispatcher.php',
            'src/InvocationMetadata.php',
            'src/Contracts/ContextResolver.php',
            'src/Contracts/OperationInvoker.php',
            'src/Contracts/RequestContext.php',
            'src/Contracts/RequestContextProvider.php',
            'src/Data/OperationInvocation.php',
            'src/Data/OperationResult.php',
            'src/Internal/CapabilityResolver.php',
            'src/Internal/CurrentRequestContext.php',
            'src/Internal/DefaultOperationInvoker.php',
            'src/Internal/ResolvedContext.php',
            'src/Internal/TrustedContextResolver.php',
        ],
        'Authorization' => [
            'src/Contracts/ClientScopeAuthorizer.php',
            'src/Contracts/OperationAuthorizer.php',
            'src/Contracts/OperationFieldAuthorizer.php',
            'src/Contracts/Principal.php',
            'src/Contracts/PrincipalResolver.php',
            'src/Contracts/ReplayAuthorizer.php',
            'src/Internal/DeclaredFieldAuthorizer.php',
            'src/Internal/DenyClientScopeAuthorizer.php',
            'src/Internal/DenyOperationAuthorizer.php',
            'src/Internal/DenyPrincipalResolver.php',
        ],
        'Validation' => [
            'src/Data/JsonObject.php',
            'src/Data/JsonValue.php',
            'src/Data/SchemaHandle.php',
            'src/Data/SchemaValidation.php',
            'src/Internal/JsonCanonical.php',
            'src/Internal/SchemaBudget.php',
            'src/Internal/SchemaProfile.php',
        ],
        'Versioning' => [
            'src/Data/ApiProfile.php',
            'src/Data/ApiVersion.php',
            'src/Data/ApiVersionStatus.php',
            'src/Data/CapabilityDescriptor.php',
            'src/Data/DeprecationDescriptor.php',
            'src/Data/EffectiveCapability.php',
            'src/Internal/ApiProfileFactory.php',
        ],
        'Idempotency' => [
            'src/Contracts/IdempotencyStore.php',
            'src/Data/IdempotencyAttempt.php',
            'src/Data/IdempotencyScope.php',
            'src/Data/StoredOperationOutcome.php',
            'src/Enums/IdempotencyRequirement.php',
            'src/Internal/IdempotentOperationInvoker.php',
            'src/Internal/SqlIdempotencyStore.php',
        ],
        'Pagination' => [
            'src/Contracts/CursorKeyring.php',
            'src/Data/CursorPage.php',
            'src/Data/CursorPayload.php',
            'src/Data/CursorPosition.php',
            'src/Internal/OrderingRegistry.php',
            'src/Internal/SignedCursorCodec.php',
        ],
        'Errors' => [
            'src/ApiError.php',
            'src/OperationErrorBoundary.php',
            'src/Contracts/ResponseSerializer.php',
            'src/Internal/EnvelopeSerializer.php',
            'src/Internal/ErrorMapper.php',
            'src/Internal/ErrorWire.php',
            'src/Internal/NegotiatingResponseSerializer.php',
            'src/Internal/ProblemDetailsSerializer.php',
            'src/Internal/RetryAfterPolicy.php',
        ],
        'Http' => [
            'src/ApiController.php',
            'src/DiscoveryController.php',
            'src/Internal/AcceptNegotiation.php',
            'src/Internal/HttpOperationResponder.php',
        ],
        'OpenApi' => [
            'src/OpenApiDocumentGenerator.php',
            'src/Commands/WriteApiSnapshots.php',
            'src/Internal/ContractSnapshotBuilder.php',
            'src/Internal/SnapshotJson.php',
        ],
        'Tools' => [
            'src/ToolServiceProvider.php',
            'src/Contracts/ToolCatalogue.php',
            'src/Contracts/ToolExecutionGuard.php',
            'src/Contracts/ToolInvoker.php',
            'src/Contracts/ToolPolicy.php',
            'src/Contracts/ToolRequestContext.php',
            'src/Contracts/ToolSchemaValidator.php',
            'src/Data/ToolCall.php',
            'src/Data/ToolCatalogueView.php',
            'src/Data/ToolDescriptor.php',
            'src/Data/ToolResult.php',
            'src/Internal/CompiledToolCatalogue.php',
            'src/Internal/DenyToolPolicy.php',
            'src/Internal/GuardedToolInvoker.php',
            'src/Internal/McpToolsProjection.php',
            'src/Internal/OpisToolSchemaValidator.php',
            'src/Internal/ReadOnlyToolExecutionGuard.php',
            'src/Internal/ToolContractHasher.php',
            'src/Internal/ToolName.php',
            'src/Internal/ToolRequestContext.php',
            'src/Internal/ToolSchemaSupervisor.php',
            'src/Internal/ToolSchemaWorker.php',
        ],
        'Laravel' => [
            'src/Support/ApiOptions.php',
        ],
    ];

    return flattenContextMoves($pkg, $map);
}

/** @return list<array{0: string, 1: string}> */
function p165Moves(string $pkg): array
{
    $map = [
        'Cost' => [
            'src/Contracts/BudgetReservation.php',
            'src/Contracts/OperationBudget.php',
            'src/Contracts/OperationCostEstimator.php',
            'src/Data/ActualCost.php',
            'src/Data/BudgetScope.php',
            'src/Data/CostInput.php',
            'src/Data/CostOptions.php',
            'src/Data/EstimatedCost.php',
            'src/Data/ThrottleStatus.php',
            'src/Laravel/CostRegistrar.php',
        ],
        'Bulk' => [
            'src/Contracts/BulkOperationStore.php',
            'src/Contracts/BulkTransferFactory.php',
            'src/Data/BulkOperation.php',
            'src/Data/BulkOperationDefinition.php',
            'src/Data/BulkOptions.php',
            'src/Data/BulkTransferSession.php',
            'src/Enums/BulkOperationStatus.php',
            'src/Commands/WorkBulkOperations.php',
            'src/Laravel/BulkRegistrar.php',
            'src/Laravel/BulkRouteRegistrar.php',
        ],
    ];

    $moves = flattenContextMoves($pkg, $map);

    foreach (glob("$pkg/src/Internal/Cost/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Cost/Internal/".basename($path)];
    }
    foreach (glob("$pkg/src/Internal/Bulk/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Bulk/Internal/".basename($path)];
    }

    return $moves;
}

/** @return list<array{0: string, 1: string}> */
function p166Moves(string $pkg): array
{
    $map = [
        'Webhooks' => [
            'src/Contracts/WebhookDeliveryStore.php',
            'src/Contracts/WebhookHttpTransport.php',
            'src/Contracts/WebhookPayloadProjector.php',
            'src/Contracts/WebhookSigner.php',
            'src/Contracts/WebhookSubscriptionStore.php',
            'src/Data/ValidatedWebhookRequest.php',
            'src/Data/WebhookDelivery.php',
            'src/Data/WebhookHttpResult.php',
            'src/Data/WebhookOptions.php',
            'src/Data/WebhookSignedHeaders.php',
            'src/Data/WebhookSubscription.php',
            'src/Data/WebhookTopic.php',
            'src/Commands/DeliverWebhooks.php',
            'src/Laravel/WebhookRegistrar.php',
            'src/Laravel/WebhookRouteRegistrar.php',
        ],
        'Laravel' => [
            'src/Commands/PruneApiOperations.php',
        ],
    ];

    $moves = flattenContextMoves($pkg, $map);

    foreach (glob("$pkg/src/Internal/Webhook/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Webhooks/Internal/".basename($path)];
    }

    return $moves;
}

/**
 * @param  array<string, list<string>>  $map
 * @return list<array{0: string, 1: string}>
 */
function flattenContextMoves(string $pkg, array $map): array
{
    $moves = [];

    foreach ($map as $context => $relatives) {
        foreach ($relatives as $relative) {
            $from = "$pkg/$relative";
            $file = basename($relative);
            $layer = layerOf($relative);
            $dest = $layer === ''
                ? "$pkg/src/$context/$file"
                : "$pkg/src/$context/$layer/$file";
            $moves[] = [$from, $dest];
        }
    }

    return $moves;
}

function layerOf(string $relative): string
{
    return match (true) {
        str_contains($relative, '/Contracts/') => 'Contracts',
        str_contains($relative, '/Data/') => 'Data',
        str_contains($relative, '/Enums/') => 'Enums',
        str_contains($relative, '/Internal/') => 'Internal',
        str_contains($relative, '/Commands/') => 'Commands',
        str_contains($relative, '/Laravel/') => 'Laravel',
        str_contains($relative, '/Support/') => 'Support',
        default => '',
    };
}

function relocate(string $root, string $from, string $to): void
{
    $fromPath = "$root/$from";
    $toPath = "$root/$to";

    if (! is_file($fromPath)) {
        if (is_file($toPath)) {
            return;
        }

        throw new RuntimeException("Missing source: $from");
    }

    if (is_file($toPath)) {
        throw new RuntimeException("Destination exists: $to");
    }
    $dir = dirname($toPath);

    if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
        throw new RuntimeException("Cannot create directory: $dir");
    }

    $git = proc_open(
        ['git', '-C', $root, 'mv', $from, $to],
        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
        $pipes,
    );
    $err = '';

    if (is_resource($git)) {
        fclose($pipes[0]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($git);

        if ($code === 0) {
            return;
        }
    }

    if (! rename($fromPath, $toPath)) {
        throw new RuntimeException("Failed to move $from -> $to ($err)");
    }
}

function patchToolSchemaWorker(string $root): void
{
    $path = "$root/packages/operations/src/Tools/Internal/ToolSchemaWorker.php";
    $src = file_get_contents($path);

    if ($src === false) {
        throw new RuntimeException("Missing ToolSchemaWorker at $path");
    }

    $updated = str_replace(
        [
            "dirname(__DIR__, 2).'/vendor/autoload.php'",
            "dirname(__DIR__, 4).'/vendor/autoload.php'",
        ],
        [
            "dirname(__DIR__, 3).'/vendor/autoload.php'",
            "dirname(__DIR__, 5).'/vendor/autoload.php'",
        ],
        $src,
    );

    if ($updated === $src) {
        return;
    }

    file_put_contents($path, $updated);
}

/**
 * @param  array<string, mixed>  $autoload
 */
function writeAutoload(string $root, array $autoload): void
{
    $path = "$root/packages/operations/composer.json";
    $json = json_decode((string) file_get_contents($path), true);

    if (! is_array($json)) {
        throw new RuntimeException('operations composer.json unreadable');
    }
    $json['autoload']['psr-4'] = $autoload;
    file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
}

/** @return array<string, mixed> */
function autoloadP164(): array
{
    return [
        'CoreX\\Api\\' => [
            'src/',
            'src/Definition/',
            'src/Invocation/',
            'src/Errors/',
            'src/Http/',
            'src/OpenApi/',
            'src/Tools/',
        ],
        'CoreX\\Api\\Contracts\\' => [
            'src/Definition/Contracts/',
            'src/Invocation/Contracts/',
            'src/Authorization/Contracts/',
            'src/Idempotency/Contracts/',
            'src/Pagination/Contracts/',
            'src/Errors/Contracts/',
            'src/Tools/Contracts/',
            'src/Contracts/',
        ],
        'CoreX\\Api\\Data\\' => [
            'src/Definition/Data/',
            'src/Invocation/Data/',
            'src/Validation/Data/',
            'src/Versioning/Data/',
            'src/Idempotency/Data/',
            'src/Pagination/Data/',
            'src/Tools/Data/',
            'src/Data/',
        ],
        'CoreX\\Api\\Enums\\' => [
            'src/Definition/Enums/',
            'src/Idempotency/Enums/',
            'src/Enums/',
        ],
        'CoreX\\Api\\Internal\\' => [
            'src/Definition/Internal/',
            'src/Invocation/Internal/',
            'src/Authorization/Internal/',
            'src/Validation/Internal/',
            'src/Versioning/Internal/',
            'src/Idempotency/Internal/',
            'src/Pagination/Internal/',
            'src/Errors/Internal/',
            'src/Http/Internal/',
            'src/OpenApi/Internal/',
            'src/Tools/Internal/',
            'src/Internal/',
        ],
        'CoreX\\Api\\Commands\\' => [
            'src/OpenApi/Commands/',
            'src/Commands/',
        ],
        'CoreX\\Api\\Support\\' => 'src/Laravel/Support/',
    ];
}

/** @return array<string, mixed> */
function autoloadP165(): array
{
    $autoload = autoloadP164();
    $autoload['CoreX\\Api\\Contracts\\'] = [
        'src/Definition/Contracts/',
        'src/Invocation/Contracts/',
        'src/Authorization/Contracts/',
        'src/Idempotency/Contracts/',
        'src/Pagination/Contracts/',
        'src/Errors/Contracts/',
        'src/Tools/Contracts/',
        'src/Cost/Contracts/',
        'src/Bulk/Contracts/',
        'src/Contracts/',
    ];
    $autoload['CoreX\\Api\\Data\\'] = [
        'src/Definition/Data/',
        'src/Invocation/Data/',
        'src/Validation/Data/',
        'src/Versioning/Data/',
        'src/Idempotency/Data/',
        'src/Pagination/Data/',
        'src/Tools/Data/',
        'src/Cost/Data/',
        'src/Bulk/Data/',
        'src/Data/',
    ];
    $autoload['CoreX\\Api\\Enums\\'] = [
        'src/Definition/Enums/',
        'src/Idempotency/Enums/',
        'src/Bulk/Enums/',
    ];
    $autoload['CoreX\\Api\\Internal\\Cost\\'] = 'src/Cost/Internal/';
    $autoload['CoreX\\Api\\Internal\\Bulk\\'] = 'src/Bulk/Internal/';
    $autoload['CoreX\\Api\\Commands\\'] = [
        'src/OpenApi/Commands/',
        'src/Bulk/Commands/',
        'src/Commands/',
    ];
    $autoload['CoreX\\Api\\Laravel\\'] = [
        'src/Laravel/',
        'src/Cost/Laravel/',
        'src/Bulk/Laravel/',
    ];

    return $autoload;
}

/** @return array<string, mixed> */
function autoloadP166(): array
{
    $autoload = autoloadP165();
    $autoload['CoreX\\Api\\Contracts\\'] = array_values(array_filter(
        array_merge($autoload['CoreX\\Api\\Contracts\\'], ['src/Webhooks/Contracts/']),
        static fn (string $dir): bool => $dir !== 'src/Contracts/',
    ));
    $autoload['CoreX\\Api\\Data\\'] = array_values(array_filter(
        array_merge($autoload['CoreX\\Api\\Data\\'], ['src/Webhooks/Data/']),
        static fn (string $dir): bool => $dir !== 'src/Data/',
    ));
    $autoload['CoreX\\Api\\Internal\\Webhook\\'] = 'src/Webhooks/Internal/';
    $autoload['CoreX\\Api\\Internal\\'] = array_values(array_filter(
        $autoload['CoreX\\Api\\Internal\\'],
        static fn (string $dir): bool => $dir !== 'src/Internal/',
    ));
    $autoload['CoreX\\Api\\Commands\\'] = [
        'src/OpenApi/Commands/',
        'src/Bulk/Commands/',
        'src/Webhooks/Commands/',
        'src/Laravel/Commands/',
    ];
    $autoload['CoreX\\Api\\Laravel\\'] = [
        'src/Laravel/',
        'src/Cost/Laravel/',
        'src/Bulk/Laravel/',
        'src/Webhooks/Laravel/',
    ];

    return $autoload;
}

function runStage(string $root, string $pkg, string $name, callable $moves, callable $autoload, ?callable $after = null): int
{
    $count = 0;
    foreach ($moves($pkg) as [$from, $to]) {
        relocate($root, $from, $to);
        fwrite(STDOUT, "moved $from\n");
        $count++;
    }
    writeAutoload($root, $autoload());

    if ($after !== null) {
        $after($root);
    }
    fwrite(STDOUT, "Stage $name: $count moves\n");

    return $count;
}

$total = 0;

if ($stage === 'p164' || $stage === 'all') {
    $total += runStage($root, $pkg, 'P16.4', p164Moves(...), autoloadP164(...), patchToolSchemaWorker(...));
}

if ($stage === 'p165' || $stage === 'all') {
    $total += runStage($root, $pkg, 'P16.5', p165Moves(...), autoloadP165(...));
}

if ($stage === 'p166' || $stage === 'all') {
    $total += runStage($root, $pkg, 'P16.6', p166Moves(...), autoloadP166(...));
}

if (! in_array($stage, ['p164', 'p165', 'p166', 'all'], true)) {
    throw new InvalidArgumentException("Unknown stage: $stage");
}

fwrite(STDOUT, "Done: $total moves\n");
cleanupEmptyDirs("$root/packages/operations/src");

function cleanupEmptyDirs(string $root): void
{
    do {
        $removed = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if (! $file->isDir()) {
                continue;
            }
            $entries = array_diff(scandir($file->getPathname()) ?: [], ['.', '..']);

            if ($entries !== []) {
                continue;
            }

            if (@rmdir($file->getPathname())) {
                fwrite(STDOUT, 'removed empty '.$file->getPathname()."\n");
                $removed++;
            }
        }
    } while ($removed > 0);
}
