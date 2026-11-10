<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $organizationId = app(OrganizationContext::class)->id();
        abort_unless(
            $organizationId
            && $request->user()?->organizations()
                ->whereKey($organizationId)
                ->wherePivot('role', 'owner')
                ->wherePivot('status', 'active')
                ->exists(),
            403,
            'Hanya owner organisasi yang dapat mengelola admin.',
        );

        return $next($request);
    }
}
