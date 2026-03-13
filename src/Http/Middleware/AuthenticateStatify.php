<?php

namespace FilaHQ\Statify\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStatify
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = config('statify.guard', 'token');

        return match ($guard) {
            'sanctum' => $this->authenticateWithSanctum($request, $next),
            default => $this->authenticateWithToken($request, $next),
        };
    }

    protected function authenticateWithToken(Request $request, Closure $next): Response
    {
        $token = config('statify.token');

        if ($token === null) {
            return $next($request);
        }

        $provided = $request->query('token') ?? $request->bearerToken();

        if (! hash_equals((string) $token, (string) $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    protected function authenticateWithSanctum(Request $request, Closure $next): Response
    {
        if (! auth('sanctum')->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
