<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class OrganizationRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register-organization', [
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()->route('home')->with('sukses', 'Permintaan pendaftaran diterima.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('organizations', 'slug')],
            'type' => ['required', 'in:education,company,community,personal'],
            'timezone' => ['required', 'timezone:all'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'owner_password' => ['required', 'confirmed', Password::min(8)],
            'terms' => ['accepted'],
        ], [
            'slug.unique' => 'Slug organisasi sudah dipakai.',
            'owner_username.unique' => 'Username sudah dipakai akun lain.',
            'owner_email.unique' => 'Email sudah dipakai akun lain.',
            'owner_password.confirmed' => 'Konfirmasi password tidak cocok.',
            'terms.accepted' => 'Setujui Syarat Penggunaan dan Kebijakan Privasi untuk melanjutkan.',
        ]);

        [$organization, $owner] = DB::transaction(function () use ($data) {
            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => Str::lower($data['slug']),
                'type' => $data['type'],
                'timezone' => $data['timezone'],
                'is_active' => true,
            ]);

            $owner = User::create([
                'nama' => $data['owner_name'],
                'username' => $data['owner_username'],
                'email' => $data['owner_email'],
                'password' => $data['owner_password'],
                'role' => 'admin',
                'is_platform_admin' => false,
                'must_change_password' => false,
            ]);

            $organization->users()->syncWithoutDetaching([
                $owner->id => ['role' => 'owner', 'status' => 'active'],
            ]);

            AuditLog::record('organization_self_registered', 'organization', $organization->id, [
                'name' => $organization->name,
                'owner_id' => $owner->id,
                'owner_username' => $owner->username,
            ], $organization->id);

            return [$organization, $owner];
        });

        Auth::login($owner);
        $token = Str::random(64);
        $owner->forceFill(['current_session_token' => $token])->save();
        $request->session()->regenerate();
        $request->session()->put('auth_token', $token);
        $request->session()->put('organization_id', $organization->id);

        try {
            $owner->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->route('verification.notice')->with(
            'sukses',
            'Organisasi '.$organization->name.' dibuat. Periksa email '.$owner->email.' untuk verifikasi.',
        );
    }
}
