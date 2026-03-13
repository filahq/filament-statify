<?php

namespace FilaHQ\Statify\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStatify
{
    public function handle(Request $request, Closure $next): Response
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
}
