<?php

namespace Daxit\OptimaClass\Commands;

use Daxit\OptimaClass\Components\Routehelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

class PublishCRMRoutesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'optima:publish-routes';

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
        $jsonFilePath = public_path('uploads/temp/rules.json');

        if (!File::exists($jsonFilePath)) {
            error("File not found: rules.json");
            return Command::FAILURE;
        }

        $routes = Routehelper::parseJsonFile($jsonFilePath);

        if ($routes === null) {
            error('Invalid JSON data.');
            return Command::FAILURE;
        }

        $groupedRoutes = Routehelper::groupRoutes($routes);

        $routeDefinitions = Routehelper::generateRouteDefinitions($groupedRoutes);

        Routehelper::writeRoutesToFile($routeDefinitions);

        info('Routes have been successfully added to site.php');

        return Command::SUCCESS;
    }
}
