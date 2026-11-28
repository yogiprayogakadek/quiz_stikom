<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isCurrentOrganizationOwner(),
            403,
            'Hanya owner organisasi yang dapat mengelola admin.',
        );

        return $next($request);
    }
}
