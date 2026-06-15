<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates Bearer tokens when present, without requiring login.
 * Lets public routes return is_subscribed / is_liked for logged-in users.
 */
class OptionalSanctumAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            $accessToken = PersonalAccessToken::findToken($request->bearerToken());

            if ($accessToken?->tokenable) {
                $user = $accessToken->tokenable;
                $request->setUserResolver(fn () => $user);
            }
        }

        return $next($request);
    }
}
