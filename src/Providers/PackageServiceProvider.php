<?php

namespace Daxit\OptimaClass\Providers;

use Illuminate\Support\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->register('Daxit\ReCaptcha\ReCaptchaServiceProvider');

        $this->app->bind('page_data', fn() => null);
    }

    public function boot()
    {
        $this->commands([
            \Daxit\OptimaClass\Commands\CreateLocalizationCommand::class,
            \Daxit\OptimaClass\Commands\PublishCRMRoutesCommand::class,
        ]);

        $this->loadViewsFrom(__DIR__.'/../Views','optima');
    }
}
