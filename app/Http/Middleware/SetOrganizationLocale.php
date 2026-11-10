<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetOrganizationLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app(OrganizationContext::class)->applyLocale();

        return $next($request);
    }
}
