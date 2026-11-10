<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerifiedWhenPresent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && filled($user->email)
            && ! $user->hasVerifiedEmail()
            && ! $request->routeIs('verification.*', 'logout', 'password.change', 'password.change.update')) {
            return redirect()->route('verification.notice');
        }

        return $next($request);
    }
}
