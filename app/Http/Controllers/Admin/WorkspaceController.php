<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkspaceRequest;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request, OrganizationContext $context): View
    {
        $ownedOrganizations = $request->user()
            ->ownedOrganizations()
            ->orderBy('name')
            ->get();

        abort_unless($ownedOrganizations->isNotEmpty(), 403, 'Halaman ini hanya tersedia untuk owner workspace.');

        $requests = WorkspaceRequest::query()
            ->where('requester_id', $request->user()->id)
            ->with('organization')
            ->latest()
            ->get();

        $currentOrganization = $context->current();

        return view('admin.workspace.index', compact('ownedOrganizations', 'requests', 'currentOrganization'));
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->ownedOrganizations()->exists(), 403, 'Hanya owner workspace yang dapat mengajukan workspace baru.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:education,company,community,personal'],
            'timezone' => ['required', 'timezone:all'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $workspaceRequest = WorkspaceRequest::create([
            'requester_id' => $user->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'timezone' => $data['timezone'],
            'note' => $data['note'] ?? null,
            'status' => WorkspaceRequest::STATUS_PENDING,
        ]);

        AuditLog::create([
            'organization_id' => null,
            'actor_id' => $user->id,
            'action' => 'workspace_requested',
            'subject_type' => 'workspace_request',
            'subject_id' => $workspaceRequest->id,
            'metadata' => [
                'name' => $workspaceRequest->name,
                'type' => $workspaceRequest->type,
                'timezone' => $workspaceRequest->timezone,
            ],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('sukses', 'Permintaan workspace '.$workspaceRequest->name.' dikirim untuk ditinjau master.');
    }
}
