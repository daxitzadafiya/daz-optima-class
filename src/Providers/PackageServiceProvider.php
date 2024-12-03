<?php

namespace Daz\OptimaClass\Providers;

use Daz\OptimaClass\Helpers\ContactUs;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->register('Daz\ReCaptcha\ReCaptchaServiceProvider');

        $this->app->bind(Request::class, function ($app) {
            return ContactUs::createFrom($app['request'], $app);
        });
    }

    public function boot()
    {
        //
    }
}
