
## About Optima Class

Register Provider in bootstrap/providers.php

- Daxit\OptimaClass\Providers\PackageServiceProvider::class

Add middleware in bootstrap/app.php inside withMiddleware

```php
use Daxit\OptimaClass\Middleware\LocaleMiddleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->use([
        LocaleMiddleware::class
    ]);
    $middleware->alias([
        'locale' => LocaleMiddleware::class
    ]);
});
```

Include site.php file in your web.php file

- require __DIR__.'/site.php';

## Usage

Please run below command for publish routes

- php artisan optima:publish-routes-command

Please run below command for create locale files

- php artisan optima:create-locale-files

Please Run below commands

- php artisan optimize:clear
- php artisan config:cache