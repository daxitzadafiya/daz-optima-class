<?php

namespace Daxit\OptimaClass\Components;

use Illuminate\Support\Facades\Artisan;
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

            $lang = $route['lang'];

            list($controllerName, $action) = explode('/', $route['route']);

            $controller = ucfirst($controllerName) . 'Controller';

            $actionName = Str::studly($action);

            $uniqueKey = "$controller|$pattern|$action";

            $groupedRoutes[$controller][$lang][$uniqueKey] = [
                'pattern' => $pattern,
                'action' => $actionName
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

        foreach ($groupedRoutes as $controller => $routes) {
            $controllerNamespace = "App\\Http\\Controllers\\$controller";
            $controllerPath = app_path("Http/Controllers/{$controller}.php");

            if (!File::exists($controllerPath)) {
                Artisan::call("make:controller {$controller}");
            }

            $definitions .= "use $controllerNamespace;\n";

            foreach ($routes as $lang => $siteRoutes) {
                foreach ($siteRoutes as $route) {
                    $methodName = $route['action'];

                    if (!self::methodExistsInController($controllerPath, $methodName)) {
                        self::addMethodToController($controllerPath, $methodName);
                    }
                }
            }
        }

        foreach ($groupedRoutes as $controller => $routes) {
            $definitions .= "\nRoute::controller($controller::class)->group(function () {\n";

            foreach($routes as $lang => $siteRoutes) {
                $definitions .= "    Route::prefix('$lang')->group(function () {\n";

                foreach ($siteRoutes as $route) {
                    $definitions .= "        Route::get('{$route['pattern']}', '{$route['action']}');\n";
                }

                $definitions .= "    });\n";
            }

            $definitions .= "});\n";
        }

        return $definitions;
    }

    protected static function methodExistsInController(string $controllerPath, string $methodName): bool
    {
        $fileContent = File::get($controllerPath);

        return preg_match("/function\s+{$methodName}\s*\(/", $fileContent) === 1;
    }

    protected static function addMethodToController(string $controllerPath, string $methodName): void
    {
        $template = <<<METHOD
                /**
                 * Handle the {$methodName} request.
                 */
                public function {$methodName}()
                {
                    // TODO: Implement {$methodName} logic
                }
            METHOD;

        $fileContent = File::get($controllerPath);

        // Insert the method before the last closing bracket of the class
        $fileContent = preg_replace(
            '/}\s*$/',
            "$template\n}",
            $fileContent
        );

        File::put($controllerPath, $fileContent);
    }

    /**
     * Write the route definitions to the site.php file.
     */
    public static function writeRoutesToFile(string $routeDefinitions): void
    {
        $filePath = base_path('routes/site.php');

        if (!File::exists($filePath)) {
            File::makeDirectory($filePath, 0644, true); // Creates the directory with proper permissions
        }

        if (File::exists($filePath)) {
            file_put_contents($filePath, "\n" . $routeDefinitions);
        }
    }
}
