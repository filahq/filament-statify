<?php

namespace FilaHQ\Statify\Http\Middleware;

use Closure;
use FilaHQ\Statify\Statify;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStatify
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = config('statify.guard', 'token');

        $authorized = function (Request $request) use ($next): Response {
            if (! app(Statify::class)->isAuthorized($request)) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            return $next($request);
        };

        return match ($guard) {
            'public' => $authorized($request),
            'sanctum' => $this->authenticateWithSanctum($request, $authorized),
            'token' => $this->authenticateWithToken($request, $authorized),
            default => response()->json(['error' => 'Invalid Statify authentication guard'], 503),
        };
    }

    protected function authenticateWithToken(Request $request, Closure $next): Response
    {
        $token = config('statify.token');

        if (! is_string($token) || trim($token) === '') {
            return response()->json(['error' => 'Statify token is not configured'], 503);
        }

        $provided = $request->bearerToken() ?? $request->query('token');

        if (! is_string($provided) || ! hash_equals($token, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    protected function authenticateWithSanctum(Request $request, Closure $next): Response
    {
        if (! class_exists(Sanctum::class) || ! config('auth.guards.sanctum')) {
            return response()->json(['error' => 'Statify Sanctum authentication is not configured'], 503);
        }

        if (! auth('sanctum')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        auth()->shouldUse('sanctum');

        $ability = config('statify.ability');

        if ($ability !== null && (! is_string($ability) || trim($ability) === '')) {
            return response()->json(['error' => 'Invalid Statify token ability'], 503);
        }

        if ($ability !== null && ! $request->user()->tokenCan($ability)) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
