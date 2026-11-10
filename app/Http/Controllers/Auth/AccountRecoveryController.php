<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountRecoveryController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        try {
            PasswordBroker::sendResetLink($request->only('email'));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('sukses', 'Jika email terdaftar, tautan reset password telah dikirim.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);
        $loginRoute = 'login';

        $status = PasswordBroker::reset($data, function (User $user, string $password) use (&$loginRoute) {
            $user->forceFill([
                'password' => Hash::make($password),
                'must_change_password' => false,
                'current_session_token' => null,
                'remember_token' => Str::random(60),
            ])->save();
            $loginRoute = $user->isPlatformAdmin() ? 'master.login' : 'login';
            event(new PasswordReset($user));
        });

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Tautan reset tidak dapat dipakai. Minta tautan baru atau coba lagi nanti.']);
        }

        return redirect()->route($loginRoute)->with('sukses', 'Password berhasil direset. Silakan masuk kembali.');
    }
}
