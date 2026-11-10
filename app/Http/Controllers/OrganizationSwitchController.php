<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OrganizationSwitchController extends Controller
{
    public function __invoke(Request $request, OrganizationContext $context): RedirectResponse
    {
        $data = $request->validate(['organization_id' => ['required', 'integer', 'exists:organizations,id']]);
        $organization = Organization::findOrFail($data['organization_id']);

        abort_unless(
            $organization->is_active
            && $request->user()->organizations()->whereKey($organization->id)->wherePivot('status', 'active')->exists(),
            403,
            'Anda bukan anggota aktif organisasi ini.',
        );

        $context->set($organization);

        return redirect()->route($request->user()->isAdmin() ? 'admin.dashboard' : 'peserta.dashboard')
            ->with('sukses', 'Workspace aktif diubah ke '.$organization->name.'.');
    }
}
