<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        $organization = app(OrganizationContext::class)->current();
        $admins = $organization->users()
            ->where('users.role', 'admin')
            ->wherePivotIn('role', ['owner', 'admin'])
            ->orderBy('users.nama')
            ->get();

        return view('admin.team.index', compact('organization', 'admins'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'password' => ['nullable', 'string', Password::min(8)],
        ]);
        $temporaryPassword = $data['password'] ?: Str::password(12, symbols: false);
        $organization = app(OrganizationContext::class)->current();

        $admin = DB::transaction(function () use ($data, $temporaryPassword, $organization) {
            $admin = User::create([
                'nama' => $data['nama'],
                'username' => $data['username'],
                'password' => $temporaryPassword,
                'role' => 'admin',
                'must_change_password' => true,
            ]);
            $organization->users()->syncWithoutDetaching([
                $admin->id => ['role' => 'admin', 'status' => 'active'],
            ]);

            return $admin;
        });

        return back()
            ->with('sukses', 'Admin organisasi ditambahkan.')
            ->with('password_baru', ['username' => $admin->username, 'password' => $temporaryPassword]);
    }

    public function destroy(User $admin): RedirectResponse
    {
        $organization = app(OrganizationContext::class)->current();
        abort_if($admin->is(auth()->user()), 422, 'Owner tidak dapat menghapus aksesnya sendiri.');
        abort_unless(
            $organization->users()->whereKey($admin->id)->wherePivot('role', 'admin')->exists(),
            404,
        );

        $organization->users()->detach($admin->id);

        return back()->with('sukses', 'Akses admin dicabut dari organisasi.');
    }
}
