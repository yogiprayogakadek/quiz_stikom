<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationMember
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            app(OrganizationContext::class)->current(),
            403,
            'Akun ini belum terdaftar pada organisasi aktif.',
        );

        return $next($request);
    }
}
