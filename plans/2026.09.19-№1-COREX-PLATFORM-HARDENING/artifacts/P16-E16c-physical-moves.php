<?php

declare(strict_types=1);

/**
 * Physical layout moves for P16.7 (modules — optional re-run) and P16.8 CRM (FQCN-stable via composer PSR-4 arrays).
 *
 * @usage php plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/artifacts/P16-E16c-physical-moves.php
 */
$root = dirname(__DIR__, 3);

/** @return list<array{0: string, 1: string}> */
function movesForCrm(string $pkg): array
{
    $moves = [];

    $actions = [
        'CreateCompany.php' => 'Company/Actions',
        'CreateContact.php' => 'Contact/Actions',
        'AssociateContactCompany.php' => 'Contact/Actions',
        'CreateLead.php' => 'Lead/Actions',
        'ConvertLead.php' => 'Lead/Actions',
        'CreateDeal.php' => 'Deal/Actions',
        'MoveDealToStage.php' => 'Deal/Actions',
        'LogActivity.php' => 'Activity/Actions',
    ];
    foreach ($actions as $file => $dest) {
        $moves[] = ["$pkg/src/Actions/$file", "$pkg/src/$dest/$file"];
    }

    $apiShared = ['CrmSchemas.php', 'CrmIdentifier.php'];
    foreach ($apiShared as $file) {
        $moves[] = ["$pkg/src/Api/$file", "$pkg/src/ApiShared/$file"];
    }

    $apiCompany = ['CreateCompanyOperation.php', 'ShowCompanyOperation.php', 'ListCompaniesOperation.php', 'CompanyPayload.php'];
    foreach ($apiCompany as $file) {
        $moves[] = ["$pkg/src/Api/$file", "$pkg/src/Company/Api/$file"];
    }

    $apiContact = [
        'CreateContactOperation.php',
        'ShowContactOperation.php',
        'ListContactsOperation.php',
        'ContactPayload.php',
        'CreateContactCompanyOperation.php',
    ];
    foreach ($apiContact as $file) {
        $moves[] = ["$pkg/src/Api/$file", "$pkg/src/Contact/Api/$file"];
    }

    $models = [
        'Company.php' => 'Company/Models',
        'Contact.php' => 'Contact/Models',
        'ContactCompany.php' => 'Contact/Models',
        'Lead.php' => 'Lead/Models',
        'Deal.php' => 'Deal/Models',
        'Activity.php' => 'Activity/Models',
        'ActivityTarget.php' => 'Activity/Models',
        'Pipeline.php' => 'Deal/Models',
        'Stage.php' => 'Deal/Models',
        'Source.php' => 'Context/Models',
    ];
    foreach ($models as $file => $dest) {
        $moves[] = ["$pkg/src/Models/$file", "$pkg/src/$dest/$file"];
    }

    foreach (glob("$pkg/src/Models/Concerns/*.php") ?: [] as $path) {
        $moves[] = [$path, "$pkg/src/Context/Models/Concerns/".basename($path)];
    }

    $data = [
        'CompanyData.php' => 'Company/Data',
        'ContactData.php' => 'Contact/Data',
        'ExecutionContext.php' => 'Context/Data',
        'Actor.php' => 'Context/Data',
    ];
    foreach ($data as $file => $dest) {
        $moves[] = ["$pkg/src/Data/$file", "$pkg/src/$dest/$file"];
    }

    $events = [
        'CompanyCreated.php' => 'Company/Events',
        'ContactCreated.php' => 'Contact/Events',
        'ContactCompanyAssociated.php' => 'Contact/Events',
        'LeadCreated.php' => 'Lead/Events',
        'LeadConverted.php' => 'Lead/Events',
        'DealCreated.php' => 'Deal/Events',
        'DealMovedToStage.php' => 'Deal/Events',
        'ActivityLogged.php' => 'Activity/Events',
    ];
    foreach ($events as $file => $dest) {
        $moves[] = ["$pkg/src/Events/$file", "$pkg/src/$dest/$file"];
    }

    $enums = [
        'LeadStatus.php' => 'Lead/Enums',
        'LeadSource.php' => 'Lead/Enums',
        'DealStatus.php' => 'Deal/Enums',
        'ActivityType.php' => 'Activity/Enums',
    ];
    foreach ($enums as $file => $dest) {
        $moves[] = ["$pkg/src/Enums/$file", "$pkg/src/$dest/$file"];
    }

    $moves[] = ["$pkg/src/Casts/DealAmount.php", "$pkg/src/Deal/Casts/DealAmount.php"];
    $moves[] = ["$pkg/src/Contracts/CrmExecutionContext.php", "$pkg/src/Context/Contracts/CrmExecutionContext.php"];

    $support = [
        'ContextGuard.php' => 'Context',
        'CrmScope.php' => 'Context',
        'Channels.php' => 'Context',
        'RecordRules.php' => 'Context/Internal',
    ];
    foreach ($support as $file => $dest) {
        $moves[] = ["$pkg/src/Support/$file", "$pkg/src/$dest/$file"];
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

$all = movesForCrm('packages/crm');

foreach ($all as [$from, $to]) {
    relocate($root, $from, $to);
    fwrite(STDOUT, "moved $from\n");
}

fwrite(STDOUT, 'Done: '.count($all)." moves\n");
