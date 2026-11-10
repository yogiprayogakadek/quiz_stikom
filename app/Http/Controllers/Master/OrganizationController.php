<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::withCount([
            'memberships as admin_count' => fn ($query) => $query->whereIn('role', ['owner', 'admin'])->where('status', 'active'),
            'memberships as participant_count' => fn ($query) => $query->where('role', 'participant')->where('status', 'active'),
            'kelas',
        ])->latest()->get();

        $platformSettings = AppSetting::current();

        return view('master.organizations.index', compact('organizations', 'platformSettings'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('organizations', 'slug')],
            'type' => ['required', 'in:education,company,community,personal'],
            'timezone' => ['required', 'timezone:all'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'owner_password' => ['nullable', 'string', Password::min(8)],
        ]);

        $temporaryPassword = $data['owner_password'] ?: Str::password(12, symbols: false);

        [$organization, $owner] = DB::transaction(function () use ($data, $temporaryPassword) {
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
                'password' => $temporaryPassword,
                'role' => 'admin',
                'must_change_password' => true,
            ]);
            $organization->users()->syncWithoutDetaching([
                $owner->id => ['role' => 'owner', 'status' => 'active'],
            ]);

            return [$organization, $owner];
        });

        return back()
            ->with('sukses', 'Organisasi '.$organization->name.' dan akun owner berhasil dibuat.')
            ->with('password_baru', ['username' => $owner->username, 'password' => $temporaryPassword]);
    }

    public function updateStatus(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $organization->update(['is_active' => $request->boolean('is_active')]);

        return back()->with(
            'sukses',
            $data['is_active']
                ? 'Organisasi '.$organization->name.' diaktifkan kembali.'
                : 'Organisasi '.$organization->name.' ditangguhkan.',
        );
    }
}
