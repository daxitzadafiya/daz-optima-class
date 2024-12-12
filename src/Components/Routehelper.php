<?php

namespace Daxit\OptimaClass\Components;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Routehelper
{
    /**
     * Parse the JSON file into an associative array.
     */
    public static function parseJsonFile(string $filePath): ?array
    {
        $jsonContent = file_get_contents($filePath);

        return json_decode($jsonContent, true);
    }

    /**
     * Group the routes by controller and format them.
     */
    public static function groupRoutes(array $routes): array
    {
        $groupedRoutes = [];

        foreach ($routes as $route) {
            $pattern = preg_replace('/<(\w+)>/', '{$1}', $route['pattern']);

            list($controllerName, $action) = explode('/', $route['route']);

            $controller = ucfirst($controllerName) . 'Controller';

            $actionName = Str::studly($action);

            $uniqueKey = "$controller|$pattern|$action";

            $groupedRoutes[$controller][$uniqueKey] = [
                'pattern' => $pattern,
                'action' => $actionName,
            ];
        }

        return $groupedRoutes;
    }

     /**
     * Generate route definitions from grouped routes.
     */
    public static function generateRouteDefinitions(array $groupedRoutes): string
    {
        $definitions = "<?php\n\n";
        $definitions .= "use Illuminate\\Support\\Facades\\Route;\n";

        foreach (array_keys($groupedRoutes) as $controller) {
            $definitions .= "use App\\Http\\Controllers\\$controller;\n";
        }

        foreach ($groupedRoutes as $controller => $routes) {
            $definitions .= "\nRoute::controller($controller::class)->group(function () {\n";

            foreach ($routes as $route) {
                $definitions .= "    Route::get('{$route['pattern']}', '{$route['action']}');\n";
            }

            $definitions .= "});\n";
        }

        return $definitions;
    }

    /**
     * Write the route definitions to the site.php file.
     */
    public static function writeRoutesToFile(string $routeDefinitions): void
    {
        $filePath = base_path('routes/site.php');

        if (File::exists($filePath)) {
            file_put_contents($filePath, "\n" . $routeDefinitions);
        }
    }
}