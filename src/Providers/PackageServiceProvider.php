<?php

namespace Daz\OptimaClass\Providers;

use Illuminate\Support\ServiceProvider;

class PackageServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->register('Daz\ReCaptcha\ReCaptchaServiceProvider');
    }

    public function boot()
    {
        //
    }
}
