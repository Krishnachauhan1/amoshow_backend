<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestoreAuthorizationHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->headers->get('Authorization')) {
            $alt = $request->header('X-Authorization')
                ?: $request->header('X-Auth-Token');

            if (is_string($alt) && $alt !== '') {
                if (! str_starts_with($alt, 'Bearer ')) {
                    $alt = 'Bearer '.$alt;
                }
                $request->headers->set('Authorization', $alt);
            }
        }

        return $next($request);
    }
}
