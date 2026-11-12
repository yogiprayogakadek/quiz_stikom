<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Models\WorkspaceRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkspaceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_permintaan_workspace_memiliki_relasi_requester_reviewer_dan_organization(): void
    {
        $requester = User::create([
            'nama' => 'Requester',
            'username' => 'requester',
            'email' => 'requester@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'requester-token',
        ]);
        $reviewer = User::create([
            'nama' => 'Reviewer',
            'username' => 'reviewer',
            'email' => 'reviewer@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'current_session_token' => 'reviewer-token',
        ]);
        $organization = Organization::create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $organization->users()->attach($requester->id, [
            'role' => 'owner',
            'status' => 'active',
        ]);

        $request = WorkspaceRequest::create([
            'requester_id' => $requester->id,
            'name' => 'Workspace Baru',
            'type' => 'education',
            'timezone' => 'Asia/Jakarta',
            'status' => WorkspaceRequest::STATUS_PENDING,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'organization_id' => $organization->id,
        ]);

        $this->assertTrue($request->requester->is($requester));
        $this->assertTrue($request->reviewer->is($reviewer));
        $this->assertTrue($request->organization->is($organization));
        $this->assertTrue($requester->ownedOrganizations()->whereKey($organization->id)->exists());
        $this->assertTrue($organization->owners()->whereKey($requester->id)->exists());
    }
}
