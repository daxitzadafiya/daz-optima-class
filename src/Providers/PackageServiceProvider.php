<?php

namespace Daz\OptimaClass\Providers;

use Illuminate\Support\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register package services
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/package.php' => config_path('package.php'),
        ]);
    }
}
