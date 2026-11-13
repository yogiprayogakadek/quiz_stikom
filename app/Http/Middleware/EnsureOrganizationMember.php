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
        if (app(OrganizationContext::class)->current()) {
            return $next($request);
        }

        if ($request->user()?->isPlatformAdmin()) {
            return redirect()
                ->route('master.organizations.index')
                ->withErrors(['workspace' => 'Akun master belum terhubung ke workspace. Pilih workspace lewat menu Organisasi.']);
        }

        abort(403, 'Akun ini belum terdaftar pada organisasi aktif.');
    }
}
