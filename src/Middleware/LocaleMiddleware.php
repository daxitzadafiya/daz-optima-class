<?php

namespace Daz\OptimaClass\Middleware;

use Closure;
use Daz\OptimaClass\Helpers\Cms;
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
