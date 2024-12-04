<?php

namespace Daz\OptimaClass\Providers;

use Illuminate\Support\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->register('Daz\ReCaptcha\ReCaptchaServiceProvider');

        $this->app->bind('page_data', fn() => null);
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../Views','optima');
    }
}
