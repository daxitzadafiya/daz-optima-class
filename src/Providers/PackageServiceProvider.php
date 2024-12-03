<?php

namespace Daz\OptimaClass\Providers;

use Daz\OptimaClass\Requests\ContactUsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->register('Daz\ReCaptcha\ReCaptchaServiceProvider');

        $this->app->bind(Request::class, function ($app) {
            return ContactUsRequest::createFrom($app['request'], $app);
        });

        $this->app->bind('page_data', fn() => null);
    }

    public function boot()
    {
        //
    }
}
