<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOpenCodeAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = trim((string) config('services.opencode.api_token', ''));
        $bearerToken = trim((string) $request->bearerToken());

        if ($configuredToken !== '' && $bearerToken !== '' && hash_equals($configuredToken, $bearerToken)) {
            return $next($request);
        }

        $user = $request->user() ?? auth('web')->user();

        if ($user !== null && $user->isAdmin()) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
