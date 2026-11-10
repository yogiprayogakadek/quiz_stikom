<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventAccessDuringMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = AppSetting::current();

        if (! $settings->maintenance_enabled
            || $request->user()?->isPlatformAdmin()
            || $request->routeIs('master.login', 'master.login.store', 'logout')) {
            return $next($request);
        }

        return response()
            ->view('errors.maintenance', ['maintenance' => $settings], 503)
            ->header('Retry-After', '300')
            ->header('Cache-Control', 'no-store');
    }
}
