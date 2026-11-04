<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $token = $request->session()->get('auth_token');

            if (blank($token) || ! hash_equals((string) $user->current_session_token, (string) $token)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()
                    ->route('login')
                    ->withErrors(['username' => 'Sesi Anda berakhir karena akun ini masuk di perangkat lain.']);
            }
        }

        return $next($request);
    }
}
