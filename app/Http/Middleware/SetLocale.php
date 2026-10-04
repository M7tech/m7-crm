<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get('locale', config('app.locale'));

        if (! in_array($locale, config('locales.supported', ['en']), true)) {
            $locale = (string) config('app.locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
