<?php

namespace Daz\OptimaClass\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

class PublishCRMRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'optima:publish-routes-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command convert rules.json file to routes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $jsonFile = "rules.json";

        $routePath = base_path('routes');

        if (!File::exists($routePath.'/site.php')) {
            File::makeDirectory($routePath.'/site.php', 0755, true);
        }

        file_put_contents($routePath.'/site.php', '');

        if (!file_exists(public_path('uploads/temp/'.$jsonFile))) {
            error("File not found: $jsonFile");
            return Command::FAILURE;
        }

        $jsonRoutes = file_get_contents(asset($jsonFile));

        $routes = json_decode($jsonRoutes, true);

        if (!$routes) {
            error('Invalid JSON data.');
            return Command::FAILURE;
        }

        $groupedRoutes = [];
        foreach ($routes as $route) {
            $pattern = $route['pattern'];

            $controllerAction = explode('/', $route['route']);

            list($controllerName, $action) = $controllerAction;

            $controller = ucfirst($controllerName) . 'Controller';

            $pattern = preg_replace('/<(\w+)>/', '{$1}', $pattern);

            $uniqueKey = "$controller|$pattern|$action";

            $groupedRoutes[$controller][$uniqueKey] = [
                'pattern' => $pattern,
                'action' => Str::studly($action)
            ];
        }

        $routeGroups = "";

        $routeGroups .= "<?php \n\n";
        $routeGroups .= "use Illuminate\Support\Facades\Route;\n";

        foreach (array_keys($groupedRoutes) as $baseController) {
            $routeGroups .= "use App\\Http\\Controllers\\$baseController;\n";
        }

        $routeGroups .= "\nRoute::group(['prefix' => '{locale}', 'middleware' => 'locale'], function() {\n";

        foreach ($groupedRoutes as $controller => $routes) {
            $routeGroups .= "\nRoute::controller($controller::class)->group(function () {\n";
            foreach ($routes as $route) {
                $routeGroups .= "    Route::get('{$route['pattern']}', '{$route['action']}');\n";
            }
            $routeGroups .= "});\n\n";
        }

        $routeGroups .= "});\n\n";

        file_put_contents($routePath.'/site.php', "\n" . $routeGroups, FILE_APPEND);

        info('Routes have been successfully added to web.php');

        return Command::SUCCESS;
    }
}
