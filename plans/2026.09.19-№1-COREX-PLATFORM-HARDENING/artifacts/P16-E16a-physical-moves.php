<?php

declare(strict_types=1);

/**
 * One-shot physical layout moves for P16.1–P16.3 (FQCN-stable via composer PSR-4 arrays).
 *
 * @usage php plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/artifacts/P16-E16a-physical-moves.php
 */
$root = dirname(__DIR__, 3);

/** @return list<array{0: string, 1: string}> */
function movesForIntl(string $pkg): array
{
    $contract = [
        'LocaleResolver.php' => 'Locale/Contracts',
        'TranslationReader.php' => 'Translation/Contracts',
        'CurrencyConverter.php' => 'Currency/Contracts',
        'CurrencyMetadata.php' => 'Currency/Contracts',
        'RateImporter.php' => 'Currency/Contracts',
        'RateProvider.php' => 'Currency/Contracts',
        'MoneyFormatter.php' => 'Formatting/Contracts',
        'GeographyProvider.php' => 'Territory/Contracts',
        'DatasetImporter.php' => 'Territory/Contracts',
        'AddressSuggestProvider.php' => 'Territory/Contracts',
    ];

    $data = [
        'LocaleInputs.php' => 'Locale/Data',
        'LocalePolicy.php' => 'Locale/Data',
        'LocaleResolution.php' => 'Locale/Data',
        'LocalizedText.php' => 'Translation/Data',
        'TranslationResult.php' => 'Translation/Data',
        'ConversionPolicy.php' => 'Currency/Data',
        'ConversionReceipt.php' => 'Currency/Data',
        'CurrencyInfo.php' => 'Currency/Data',
        'RateBatch.php' => 'Currency/Data',
        'RateImportReceipt.php' => 'Currency/Data',
        'RateLookup.php' => 'Currency/Data',
        'RateQuery.php' => 'Currency/Data',
        'RateSnapshot.php' => 'Currency/Data',
        'FormatPolicy.php' => 'Formatting/Data',
        'FormattedMoney.php' => 'Formatting/Data',
        'AddressQuery.php' => 'Territory/Data',
        'DatasetCandidate.php' => 'Territory/Data',
        'DatasetGeneration.php' => 'Territory/Data',
        'DatasetManifest.php' => 'Territory/Data',
        'DatasetReceipt.php' => 'Territory/Data',
        'GeographyQuery.php' => 'Territory/Data',
        'ReferenceCursor.php' => 'Territory/Data',
        'ReferenceIdentity.php' => 'Territory/Data',
        'ReferencePage.php' => 'Territory/Data',
        'SuggestionLimits.php' => 'Territory/Data',
        'SuggestionResult.php' => 'Territory/Data',
    ];

    $exceptions = [
        'InvalidLocaleException.php' => 'Locale/Exceptions',
        'InvalidRateException.php' => 'Currency/Exceptions',
        'SnapshotExpiredException.php' => 'Currency/Exceptions',
        'UnsupportedFormattingException.php' => 'Formatting/Exceptions',
        'AccessDeniedException.php' => 'Territory/Exceptions',
        'OperationConflictException.php' => 'Territory/Exceptions',
        'SchemaMismatchException.php' => 'Territory/Exceptions',
    ];

    $moves = [];
    foreach ($contract as $file => $dest) {
        $moves[] = ["$pkg/src/Contracts/$file", "$pkg/src/$dest/$file"];
    }
    foreach ($data as $file => $dest) {
        $moves[] = ["$pkg/src/Data/$file", "$pkg/src/$dest/$file"];
    }
    foreach ($exceptions as $file => $dest) {
        $moves[] = ["$pkg/src/Exceptions/$file", "$pkg/src/$dest/$file"];
    }

    $localeInternal = [
        'Bcp47Validator.php',
        'DefaultLocaleResolver.php',
        'LocalePolicyBuilder.php',
        'RequestLocaleScope.php',
    ];
    foreach ($localeInternal as $file) {
        $moves[] = ["$pkg/src/Internal/Locale/$file", "$pkg/src/Locale/Internal/$file"];
    }
    $moves[] = ["$pkg/src/Internal/Locale/LaravelTranslationReader.php", "$pkg/src/Translation/Internal/LaravelTranslationReader.php"];

    $currencyMoney = [
        'BrickCurrencyConverter.php',
        'BrickCurrencyMetadata.php',
        'RateSnapshotMapper.php',
        'SqlRateImporter.php',
        'SqlRateProvider.php',
    ];
    foreach ($currencyMoney as $file) {
        $moves[] = ["$pkg/src/Internal/Money/$file", "$pkg/src/Currency/Internal/$file"];
    }
    $moves[] = ["$pkg/src/Internal/Money/PlainMoneyFormatter.php", "$pkg/src/Formatting/Internal/PlainMoneyFormatter.php"];

    foreach (glob("$pkg/src/Internal/Address/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Territory/Internal/Address/".basename($path)];
    }
    foreach (glob("$pkg/src/Internal/Reference/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Territory/Internal/Reference/".basename($path)];
    }

    return $moves;
}

/** @return list<array{0: string, 1: string}> */
function movesForNotifications(string $pkg): array
{
    $contract = [
        'NotificationCenter.php' => 'Inbox/Contracts',
        'NotificationPolicy.php' => 'Inbox/Contracts',
        'NotificationChannelDriver.php' => 'Delivery/Contracts',
        'RecipientResolver.php' => 'Delivery/Contracts',
        'DigestBuilder.php' => 'Digest/Contracts',
        'PreferenceRepository.php' => 'Preferences/Contracts',
    ];

    $data = [
        'CancelResult.php' => 'Inbox/Data',
        'InboxQuery.php' => 'Inbox/Data',
        'NotificationAction.php' => 'Inbox/Data',
        'NotificationContext.php' => 'Inbox/Data',
        'NotificationInput.php' => 'Inbox/Data',
        'NotificationRef.php' => 'Inbox/Data',
        'NotificationSubject.php' => 'Inbox/Data',
        'EntityReference.php' => 'Inbox/Data',
        'ChannelDescriptor.php' => 'Delivery/Data',
        'DeliveryClaim.php' => 'Delivery/Data',
        'DeliveryInput.php' => 'Delivery/Data',
        'DeliveryOutcome.php' => 'Delivery/Data',
        'DeliveryTarget.php' => 'Delivery/Data',
        'RecipientReference.php' => 'Delivery/Data',
        'DigestRef.php' => 'Digest/Data',
        'DigestWindow.php' => 'Digest/Data',
        'NotificationPreference.php' => 'Preferences/Data',
        'PreferenceInput.php' => 'Preferences/Data',
    ];

    $inboxInternal = [
        'SqlNotificationCenter.php',
        'DenyNotificationPolicy.php',
        'NotificationAccessDenied.php',
        'NotificationOperationConflict.php',
    ];
    $deliveryInternal = [
        'ChannelDrivers.php',
        'DeliveryCoordinator.php',
        'DeliveryStore.php',
        'InAppChannelDriver.php',
        'NullRecipientResolver.php',
    ];

    $moves = [];
    foreach ($contract as $file => $dest) {
        $moves[] = ["$pkg/src/Contracts/$file", "$pkg/src/$dest/$file"];
    }
    foreach ($data as $file => $dest) {
        $moves[] = ["$pkg/src/Data/$file", "$pkg/src/$dest/$file"];
    }
    foreach ($inboxInternal as $file) {
        $moves[] = ["$pkg/src/Internal/$file", "$pkg/src/Inbox/Internal/$file"];
    }
    foreach ($deliveryInternal as $file) {
        $moves[] = ["$pkg/src/Internal/$file", "$pkg/src/Delivery/Internal/$file"];
    }
    $moves[] = ["$pkg/src/Internal/SettingsPreferenceRepository.php", "$pkg/src/Preferences/Internal/SettingsPreferenceRepository.php"];
    $moves[] = ["$pkg/src/Internal/SqlDigestBuilder.php", "$pkg/src/Digest/Internal/SqlDigestBuilder.php"];

    foreach (glob("$pkg/src/Internal/Digest/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Digest/Internal/".basename($path)];
    }

    $moves[] = ["$pkg/src/Jobs/DeliverNotification.php", "$pkg/src/Delivery/Jobs/DeliverNotification.php"];
    $moves[] = ["$pkg/src/Jobs/DeliverDigest.php", "$pkg/src/Digest/Jobs/DeliverDigest.php"];
    $moves[] = ["$pkg/src/Commands/SweepNotificationDeliveries.php", "$pkg/src/Delivery/Commands/SweepNotificationDeliveries.php"];

    return $moves;
}

/** @return list<array{0: string, 1: string}> */
function movesForTasks(string $pkg): array
{
    $taskContracts = [
        'CreateTaskCommand.php',
        'UpdateTaskCommand.php',
        'CompleteTaskCommand.php',
        'MoveTaskToStageCommand.php',
    ];
    $accessContracts = [
        'TaskAccess.php',
        'TaskMutationAccess.php',
        'TaskPlanningAccess.php',
    ];
    $planningContracts = [
        'TaskPlanningCommand.php',
        'TaskOperationReceipts.php',
    ];

    $moves = [];
    foreach ($taskContracts as $file) {
        $moves[] = ["$pkg/src/Contracts/$file", "$pkg/src/Task/Contracts/$file"];
    }
    foreach ($accessContracts as $file) {
        $moves[] = ["$pkg/src/Contracts/$file", "$pkg/src/Access/Contracts/$file"];
    }
    foreach ($planningContracts as $file) {
        $moves[] = ["$pkg/src/Contracts/$file", "$pkg/src/Planning/Contracts/$file"];
    }

    $taskData = [
        'ActorData.php',
        'CompleteTaskData.php',
        'EntityReference.php',
        'MoveTaskToStageData.php',
        'TaskDraft.php',
        'TaskReference.php',
        'UpdateTaskData.php',
    ];
    $planningData = [
        'PlanningOperation.php',
        'PlanningSubject.php',
        'PlanningToken.php',
        'TaskOperationKind.php',
        'TaskOperationReceipt.php',
    ];
    foreach ($taskData as $file) {
        $moves[] = ["$pkg/src/Data/$file", "$pkg/src/Task/Data/$file"];
    }
    foreach ($planningData as $file) {
        $moves[] = ["$pkg/src/Data/$file", "$pkg/src/Planning/Data/$file"];
    }
    $moves[] = ["$pkg/src/Data/ProjectDraft.php", "$pkg/src/Project/Data/ProjectDraft.php"];
    $moves[] = ["$pkg/src/Data/ProjectReference.php", "$pkg/src/Project/Data/ProjectReference.php"];
    $moves[] = ["$pkg/src/Data/SprintDraft.php", "$pkg/src/Sprint/Data/SprintDraft.php"];
    $moves[] = ["$pkg/src/Data/SprintReference.php", "$pkg/src/Sprint/Data/SprintReference.php"];
    $moves[] = ["$pkg/src/Data/TimeLogDraft.php", "$pkg/src/Time/Data/TimeLogDraft.php"];
    $moves[] = ["$pkg/src/Data/TimeLogReference.php", "$pkg/src/Time/Data/TimeLogReference.php"];

    foreach (glob("$pkg/src/Actions/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Task/Actions/".basename($path)];
    }
    foreach (glob("$pkg/src/Events/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Task/Events/".basename($path)];
    }

    $taskEnums = ['TaskPriority.php', 'TaskStatus.php'];
    foreach ($taskEnums as $file) {
        $moves[] = ["$pkg/src/Enums/$file", "$pkg/src/Task/Enums/$file"];
    }
    $moves[] = ["$pkg/src/Enums/ProjectStatus.php", "$pkg/src/Project/Enums/ProjectStatus.php"];
    $moves[] = ["$pkg/src/Enums/SprintStatus.php", "$pkg/src/Sprint/Enums/SprintStatus.php"];
    $moves[] = ["$pkg/src/Enums/TimeLogSource.php", "$pkg/src/Time/Enums/TimeLogSource.php"];

    $taskModels = ['Stage.php', 'Task.php', 'TaskTarget.php', 'TaskWatcher.php'];
    foreach ($taskModels as $file) {
        $moves[] = ["$pkg/src/Models/$file", "$pkg/src/Task/Models/$file"];
    }
    $moves[] = ["$pkg/src/Models/Project.php", "$pkg/src/Project/Models/Project.php"];
    $moves[] = ["$pkg/src/Models/Sprint.php", "$pkg/src/Sprint/Models/Sprint.php"];
    $moves[] = ["$pkg/src/Models/TimeLog.php", "$pkg/src/Time/Models/TimeLog.php"];
    $moves[] = ["$pkg/src/Models/Timer.php", "$pkg/src/Time/Models/Timer.php"];

    foreach (glob("$pkg/src/Internal/Planning/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Planning/Internal/".basename($path)];
    }

    $moves[] = ["$pkg/src/Exceptions/InvalidPlanningDraft.php", "$pkg/src/Planning/Exceptions/InvalidPlanningDraft.php"];
    $moves[] = ["$pkg/src/Exceptions/PlanningConflict.php", "$pkg/src/Planning/Exceptions/PlanningConflict.php"];
    $moves[] = ["$pkg/src/Exceptions/TasksModuleDisabled.php", "$pkg/src/Access/Exceptions/TasksModuleDisabled.php"];

    foreach (glob("$pkg/src/Support/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Access/Internal/".basename($path)];
    }

    return $moves;
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

    if (is_resource($git)) {
        fclose($pipes[0]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($git);

        if ($code === 0) {
            return;
        }
    }

    if (! rename($fromPath, $toPath)) {
        throw new RuntimeException("Failed to move $from -> $to ($err ?? '')");
    }
}

$all = [
    ...movesForIntl('packages/intl'),
    ...movesForNotifications('packages/notifications'),
    ...movesForTasks('packages/tasks'),
];

foreach ($all as [$from, $to]) {
    relocate($root, $from, $to);
    fwrite(STDOUT, "moved $from\n");
}

fwrite(STDOUT, 'Done: '.count($all)." moves\n");
