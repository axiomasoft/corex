<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Scheduling\Contracts\DueWakeupHandler;
use CoreX\Scheduling\Data\WakeupDelivery;
use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Data\SearchQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ScenarioWakeup implements DueWakeupHandler
{
    public function handle(WakeupDelivery $delivery): void
    {
        $database = DB::connection();
        app(CurrentWorkScope::class)->assert($delivery->request->scope, $database);
        $page = app(SearchProvider::class)->query($delivery->request->scope, new SearchQuery(ScenarioSearchSchema::ENTITY, ScenarioSearchSchema::hash(), 'equipment'));

        if ($page->candidates[0]?->record->id !== Scenario::RECORD) {
            throw new RuntimeException('The installed wakeup did not query its real Search target.');
        }
        $database->getSchemaBuilder()->create('scenario_wakeup_publication', static fn (Blueprint $table) => $table->string('reference')->primary());
        $database->table('scenario_wakeup_publication')->insert(['reference' => $delivery->request->requestKey]);
        $database->table('scenario_effects')->insert(['reference' => $delivery->request->requestKey, 'marker' => $database->table('scenario_records')->value('marker')]);
    }
}
