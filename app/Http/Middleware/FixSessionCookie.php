<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FixSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $domain = (string) config('session.domain');

        if ($domain !== '') {
            $root = ltrim($domain, '.');
            if ($host !== $root && ! str_ends_with($host, '.'.$root)) {
                config(['session.domain' => null]);
            }
        }

        if (config('session.secure') && ! $request->isSecure()) {
            config(['session.secure' => false]);
        }

        return $next($request);
    }
}
