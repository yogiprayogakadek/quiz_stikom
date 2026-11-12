<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkspaceRequest;
use App\Services\OrganizationOwnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OwnerController extends Controller
{
    public function index(): View
    {
        $owners = User::query()
            ->where('role', 'admin')
            ->where('is_platform_admin', false)
            ->with(['ownedOrganizations' => fn ($query) => $query->orderBy('name')])
            ->withCount(['ownedOrganizations', 'organizations'])
            ->withExists([
                'workspaceRequests as has_pending_workspace_request' => fn ($query) => $query
                    ->where('status', WorkspaceRequest::STATUS_PENDING),
            ])
            ->orderBy('nama')
            ->get();

        $pendingRequests = WorkspaceRequest::query()
            ->where('status', WorkspaceRequest::STATUS_PENDING)
            ->with('requester')
            ->oldest()
            ->get();

        return view('master.owners.index', compact('owners', 'pendingRequests'));
    }

    public function destroy(User $owner): RedirectResponse
    {
        DB::transaction(function () use ($owner) {
            $locked = User::query()
                ->whereKey($owner->getKey())
                ->withCount(['ownedOrganizations', 'organizations'])
                ->withExists([
                    'workspaceRequests as has_pending_workspace_request' => fn ($query) => $query
                        ->where('status', WorkspaceRequest::STATUS_PENDING),
                ])
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($locked->isAdmin() && ! $locked->isPlatformAdmin(), 404);

            $blocker = $locked->orphanDeletionBlocker();

            if ($blocker !== null) {
                abort(422, match ($blocker) {
                    'owned' => 'Owner ini masih memiliki workspace.',
                    'member' => 'Akun ini masih terdaftar di sebuah workspace.',
                    'pending' => 'Akun ini masih memiliki permintaan workspace pending.',
                    default => 'Akun ini masih terhubung ke workspace.',
                });
            }

            $metadata = [
                'name' => $locked->nama,
                'username' => $locked->username,
                'email' => $locked->email,
            ];

            $locked->forceFill(['current_session_token' => null])->save();
            $locked->delete();

            AuditLog::create([
                'organization_id' => null,
                'actor_id' => auth()->id(),
                'action' => 'owner_deleted',
                'subject_type' => 'user',
                'subject_id' => $locked->id,
                'metadata' => $metadata,
                'ip_address' => request()->ip(),
            ]);
        });

        return redirect()
            ->route('master.owners.index')
            ->with('sukses', 'Akun owner tanpa workspace dihapus.');
    }

    public function editTransfer(Organization $organization): View
    {
        $currentOwners = $organization->owners()->orderBy('nama')->get();

        $candidates = User::query()
            ->where('role', 'admin')
            ->where('is_platform_admin', false)
            ->when($currentOwners->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $currentOwners->pluck('id')))
            ->withCount('ownedOrganizations')
            ->orderBy('nama')
            ->get();

        return view('master.organizations.owner', compact('organization', 'currentOwners', 'candidates'));
    }

    public function updateTransfer(
        Request $request,
        Organization $organization,
        OrganizationOwnership $ownership,
    ): RedirectResponse {
        $data = $request->validate([
            'new_owner_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'admin')->where('is_platform_admin', false)->whereNull('deleted_at')),
            ],
            'previous_owner_action' => ['required', 'in:keep_admin,remove'],
        ], [
            'new_owner_id.exists' => 'Owner baru harus akun admin yang aktif.',
        ]);

        $newOwner = User::query()
            ->whereKey($data['new_owner_id'])
            ->where('role', 'admin')
            ->where('is_platform_admin', false)
            ->firstOrFail();

        $ownership->transfer(
            $organization,
            $newOwner,
            $data['previous_owner_action'] === 'remove',
        );

        return redirect()
            ->route('master.organizations.index')
            ->with('sukses', 'Kepemilikan workspace '.$organization->name.' dipindahkan ke '.$newOwner->nama.'.');
    }
}
