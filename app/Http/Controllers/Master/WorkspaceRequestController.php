<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkspaceRequest;
use App\Services\OrganizationProvisioning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkspaceRequestController extends Controller
{
    public function approveForm(WorkspaceRequest $workspaceRequest): View
    {
        abort_unless($workspaceRequest->status === WorkspaceRequest::STATUS_PENDING, 404);

        return view('master.workspace-requests.approve', [
            'workspaceRequest' => $workspaceRequest,
            'requester' => $workspaceRequest->requester,
            'suggestedSlug' => $this->suggestedSlug($workspaceRequest->name),
        ]);
    }

    public function approve(
        Request $request,
        WorkspaceRequest $workspaceRequest,
        OrganizationProvisioning $provisioning,
    ): RedirectResponse {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('organizations', 'slug')],
        ]);

        $organization = DB::transaction(function () use ($workspaceRequest, $data, $provisioning) {
            $locked = WorkspaceRequest::query()
                ->whereKey($workspaceRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== WorkspaceRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Permintaan ini sudah ditinjau.',
                ]);
            }

            $requester = User::query()
                ->whereKey($locked->requester_id)
                ->where('role', 'admin')
                ->whereNull('deleted_at')
                ->first();

            if (! $requester) {
                throw ValidationException::withMessages([
                    'requester' => 'Akun pemohon tidak aktif. Permintaan tidak dapat disetujui.',
                ]);
            }

            $organization = $provisioning->createForOwner([
                'name' => $locked->name,
                'slug' => $data['slug'],
                'type' => $locked->type,
                'timezone' => $locked->timezone,
            ], $requester, [
                'source' => 'workspace_request',
                'request_id' => $locked->id,
            ]);

            $locked->update([
                'status' => WorkspaceRequest::STATUS_APPROVED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'organization_id' => $organization->id,
            ]);

            AuditLog::record('workspace_request_approved', 'workspace_request', $locked->id, [
                'requester_id' => $requester->id,
                'organization_id' => $organization->id,
            ], $organization->id);

            return $organization;
        });

        return redirect()
            ->route('master.owners.index')
            ->with('sukses', 'Workspace '.$organization->name.' disetujui dan dibuat untuk '.$organization->owners()->first()?->nama.'.');
    }

    public function reject(Request $request, WorkspaceRequest $workspaceRequest): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($workspaceRequest, $data, $request) {
            $locked = WorkspaceRequest::query()
                ->whereKey($workspaceRequest->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== WorkspaceRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Permintaan ini sudah ditinjau.',
                ]);
            }

            $locked->update([
                'status' => WorkspaceRequest::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'rejection_reason' => $data['rejection_reason'],
            ]);

            AuditLog::create([
                'organization_id' => null,
                'actor_id' => auth()->id(),
                'action' => 'workspace_request_rejected',
                'subject_type' => 'workspace_request',
                'subject_id' => $locked->id,
                'metadata' => [
                    'requester_id' => $locked->requester_id,
                    'reason' => $data['rejection_reason'],
                ],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()
            ->route('master.owners.index')
            ->with('sukses', 'Permintaan workspace '.$workspaceRequest->name.' ditolak.');
    }

    private function suggestedSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $suffix = 2;

        while (Organization::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
