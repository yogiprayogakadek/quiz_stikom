<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkspaceRequest;
use App\Services\OrganizationOwnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ->withCount('ownedOrganizations')
            ->orderBy('nama')
            ->get();

        $pendingRequests = WorkspaceRequest::query()
            ->where('status', WorkspaceRequest::STATUS_PENDING)
            ->with('requester')
            ->oldest()
            ->get();

        return view('master.owners.index', compact('owners', 'pendingRequests'));
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
