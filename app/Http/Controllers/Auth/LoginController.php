<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Organization;
use App\Models\ParticipantLoginActivity;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showOrganization(Organization $organization, OrganizationContext $context): View|RedirectResponse
    {
        abort_unless($organization->is_active, 404);
        if (Auth::check()) {
            return redirect()->route(Auth::user()->isAdmin() ? 'admin.dashboard' : 'peserta.dashboard');
        }

        $context->set($organization);
        $context->applyLocale($organization);

        return view('auth.login', ['organization' => $organization]);
    }

    public function showMaster(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route(Auth::user()->isPlatformAdmin() ? 'master.organizations.index' : 'home');
        }

        session()->forget('organization_id');
        app(OrganizationContext::class)->applyLocale();

        return view('auth.login', ['masterLogin' => true]);
    }

    public function login(LoginRequest $request, ?Organization $organization = null): RedirectResponse
    {
        $masterLogin = $request->routeIs('master.login.store');

        if (! $masterLogin) {
            abort_unless($organization?->is_active, 404);
            app(OrganizationContext::class)->set($organization);
            app(OrganizationContext::class)->applyLocale($organization);
        }

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
        if ($masterLogin && ! $user->isPlatformAdmin()) {
            return $this->rejectLogin($request, 'Akun ini tidak memiliki akses master.', route('master.login'));
        }
        if (! $masterLogin && $user->isPlatformAdmin()) {
            return $this->rejectLogin($request, 'Gunakan halaman login khusus master.', route('master.login'));
        }

        $requestedOrganizationId = $request->session()->get('organization_id');
        if ($requestedOrganizationId && ! $user->organizations()
            ->whereKey($requestedOrganizationId)
            ->where('organizations.is_active', true)
            ->wherePivot('status', 'active')
            ->exists()) {
            Auth::logout();
            $request->session()->regenerateToken();

            return redirect()->route('organizations.login', $organization)
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Akun ini tidak dapat mengakses organisasi tersebut.']);
        }
        if (! $requestedOrganizationId
            && ! $user->isPlatformAdmin()
            && ! $user->organizations()->where('organizations.is_active', true)->wherePivot('status', 'active')->exists()) {
            return $this->rejectLogin($request, 'Organisasi akun ini sedang tidak aktif. Hubungi pengelola platform.', route('home'));
        }

        $token = Str::random(64);
        $user->forceFill(['current_session_token' => $token])->save();

        $request->session()->put('auth_token', $token);

        if ($user->role === 'peserta') {
            ParticipantLoginActivity::recordAndBroadcast($user, 'login');
        }

        if ($user->wajibGantiPassword()) {
            return redirect()->route('password.change')
                ->with('peringatan', 'Ini adalah login pertama Anda. Silakan ganti password Anda terlebih dahulu.');
        }

        if ($user->isPlatformAdmin()) {
            return redirect()->route('master.organizations.index')->with('sukses', 'Selamat datang, '.$user->nama.'.');
        }

        $destination = $user->isAdmin() ? route('admin.dashboard') : route('peserta.dashboard');

        return redirect()->intended($destination)->with('sukses', 'Selamat datang, '.$user->nama.'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();
        $organization = app(OrganizationContext::class)->current();
        $location = $user?->isPlatformAdmin()
            ? route('master.login')
            : ($organization ? route('organizations.login', $organization) : route('home'));

        if ($user !== null) {
            User::whereKey($user->id)->update(['current_session_token' => null]);

            if ($user->role === 'peserta') {
                ParticipantLoginActivity::recordAndBroadcast($user, 'logout', 'manual');
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to($location)->with('sukses', 'Anda sudah keluar.');
    }

    private function rejectLogin(Request $request, string $message, string $location): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to($location)
            ->withInput($request->only('username'))
            ->withErrors(['username' => $message]);
    }
}
