<?php

declare(strict_types=1);

namespace App\TransportProof;

use App\Scenario\Scenario;
use Illuminate\Support\Facades\Artisan;

final class CarrierSnapshot
{
    public static function capture(Scenario $proof, string $name): array
    {
        $directory = getenv('COREX_SCENARIO_BARRIERS').'/'.$name;
        $arguments = ['directory' => $directory, '--major' => 'v2'];
        $generated = Artisan::call('corex:operations:snapshot', $arguments);
        $checked = Artisan::call('corex:operations:snapshot', [...$arguments, '--check' => true]);
        $proof->same([0, 0], [$generated, $checked], 'Normal installed generator and exact --check succeed.');
        $names = array_values(array_diff(scandir($directory), ['.', '..']));
        $proof->same(['errors.json', 'manifest.json', 'openapi.json', 'operations.json', 'webhooks.json'], $names, 'There are exactly five mounted-carrier outputs.');
        $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $operations = json_decode(file_get_contents($directory.'/operations.json'), true, flags: JSON_THROW_ON_ERROR);
        $proof->same('v2', $manifest['major'], 'Native generator produces the current public major.');
        $proof->same(['v2'], array_values(array_unique(array_column($operations, 'version'))), 'Every carrier declaration is v2.');
        $routesExit = Artisan::call('route:list', ['--json' => true]);
        $routeList = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $proof->same(0, $routesExit, 'Laravel normal route listing succeeds.');
        $routes = array_values(array_filter($routeList, static fn (array $route): bool => str_starts_with($route['uri'], 'api/') || $route['uri'] === 'mcp'));
        $proof->check(array_any($routes, static fn (array $route): bool => $route['uri'] === 'mcp'), 'The installed carrier composition includes the real opt-in endpoint.');

        return [
            'files' => array_combine($names, array_map(static fn (string $file): string => hash_file('sha256', $directory.'/'.$file), $names)),
            'manifest' => $manifest, 'operations' => array_column($operations, 'id'), 'routes' => $routes, 'command_exits' => [$generated, $checked, $routesExit],
        ];
    }
}
