<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rules = [
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ];
        if (! $user->must_change_password) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $request->validate($rules, [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini tidak cocok.',
            'password.required' => 'Password baru wajib diisi.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $token = Str::random(64);
        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'must_change_password' => false,
            'current_session_token' => $token,
        ])->save();
        $request->session()->put('auth_token', $token);

        return redirect()->route($user->isAdmin() ? 'admin.dashboard' : 'peserta.dashboard')
            ->with('sukses', 'Password berhasil diperbarui. Sesi lain pada akun ini telah diakhiri.');
    }
}
