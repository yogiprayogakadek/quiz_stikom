<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->isAdmin() ? 'admin.dashboard' : 'peserta.dashboard');
        }

        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $key = Str::lower($request->input('username')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $detik = RateLimiter::availableIn($key);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik."]);
        }

        $kredensial = $request->only('username', 'password');

        if (! Auth::attempt($kredensial, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Username atau password tidak cocok.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = $request->user();
        $token = Str::random(64);
        $user->forceFill(['current_session_token' => $token])->save();

        $request->session()->put('auth_token', $token);

        if ($user->wajibGantiPassword()) {
            return redirect()->route('password.change')
                ->with('peringatan', 'Ini adalah login pertama Anda. Silakan ganti password Anda terlebih dahulu.');
        }

        return redirect()->intended(
            $user->isAdmin() ? route('admin.dashboard') : route('peserta.dashboard')
        )->with('sukses', 'Selamat datang, '.$user->nama.'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            User::whereKey($user->id)->update(['current_session_token' => null]);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('sukses', 'Anda sudah keluar.');
    }
}
