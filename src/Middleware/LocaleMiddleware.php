<?php

namespace Daxit\OptimaClass\Middleware;

use Closure;
use Daxit\OptimaClass\Helpers\Cms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class LocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $excludedPaths = ['css', 'js', 'images', 'assets', 'fonts', '_debugbar'];
        $excludedExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'woff', 'woff2', 'ttf', 'otf'];

        $path = $request->path();

        foreach ($excludedPaths as $excludedPath) {
            if (str_starts_with($path, $excludedPath)) {
                return $next($request);
            }
        }

        foreach ($excludedExtensions as $extension) {
            if (str_ends_with($path, '.' . $extension)) {
                return $next($request);
            }
        }

        $locale = $request->segment(1);

        $availableLocales = Cms::siteLanguages();

        $remove_lang = Config::get('params.remove_lang', []);

        $availableLocales = array_values(array_filter($availableLocales, function($item) use ($remove_lang) {
            return !in_array((isset($item['key']) && !empty($item['key']) ? strtolower($item['key']) : $item), $remove_lang);
        }));

        if(in_array($locale, $availableLocales)) {
            App::setLocale($locale);
        } else {
            App::setLocale("en");
            return redirect('/' . App::getLocale() . '/' . ltrim($request->path(), '/'));
        }

        return $next($request);
    }
}
