<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OrganizationUsage;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
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
        $auditLogs = AuditLog::query()
            ->where('organization_id', $organization->id)
            ->whereIn('action', ['admin_added', 'admin_revoked', 'participant_added', 'participant_removed'])
            ->with('actor')
            ->latest()
            ->limit(25)
            ->get();

        return view('admin.team.index', compact('organization', 'admins', 'auditLogs'));
    }

    public function store(Request $request, OrganizationUsage $usage): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', Password::min(8)],
        ], [
            'email.required' => 'Email wajib diisi untuk admin baru.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email sudah dipakai akun lain.',
        ]);
        $temporaryPassword = $data['password'] ?? Str::password(12, symbols: false);
        $organization = app(OrganizationContext::class)->current();

        if (! $usage->canAddAdmin($organization->id)) {
            throw ValidationException::withMessages([
                'nama' => 'Kuota admin workspace penuh ('.$usage->adminLimit($organization->id).'). Hubungi master platform untuk menaikkan batas.',
            ]);
        }

        $admin = DB::transaction(function () use ($data, $temporaryPassword, $organization) {
            $admin = User::create([
                'nama' => $data['nama'],
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $temporaryPassword,
                'role' => 'admin',
                'must_change_password' => true,
            ]);
            $organization->users()->syncWithoutDetaching([
                $admin->id => ['role' => 'admin', 'status' => 'active'],
            ]);
            AuditLog::record('admin_added', 'user', $admin->id, [
                'name' => $admin->nama,
                'username' => $admin->username,
                'role' => 'admin',
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

        DB::transaction(function () use ($organization, $admin) {
            $organization->users()->detach($admin->id);
            AuditLog::record('admin_revoked', 'user', $admin->id, [
                'name' => $admin->nama,
                'username' => $admin->username,
                'role' => 'admin',
            ]);
        });

        return back()->with('sukses', 'Akses admin dicabut dari organisasi.');
    }
}
