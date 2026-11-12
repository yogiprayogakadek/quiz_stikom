<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
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
            'owner_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'owner_password' => ['nullable', 'string', Password::min(8)],
        ], [
            'owner_email.required' => 'Email owner wajib diisi.',
            'owner_email.email' => 'Format email owner tidak valid.',
            'owner_email.unique' => 'Email owner sudah dipakai akun lain.',
        ]);

        $temporaryPassword = $data['owner_password'] ?? Str::password(12, symbols: false);

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
                'email' => $data['owner_email'],
                'password' => $temporaryPassword,
                'role' => 'admin',
                'must_change_password' => true,
            ]);
            $organization->users()->syncWithoutDetaching([
                $owner->id => ['role' => 'owner', 'status' => 'active'],
            ]);
            AuditLog::record('organization_created', 'organization', $organization->id, [
                'name' => $organization->name,
                'owner_id' => $owner->id,
                'owner_username' => $owner->username,
            ], $organization->id);

            return [$organization, $owner];
        });

        return back()
            ->with('sukses', 'Organisasi '.$organization->name.' dan akun owner berhasil dibuat.')
            ->with('password_baru', ['username' => $owner->username, 'password' => $temporaryPassword]);
    }

    public function editLimits(Organization $organization, OrganizationUsage $usage): View
    {
        return view('master.organizations.limits', [
            'organization' => $organization,
            'summary' => $usage->summary($organization->id),
        ]);
    }

    public function editName(Organization $organization): View
    {
        return view('master.organizations.name', ['organization' => $organization]);
    }

    public function updateName(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $previous = $organization->name;

        if ($previous !== $data['name']) {
            $organization->update(['name' => $data['name']]);

            AuditLog::record('organization_renamed', 'organization', $organization->id, [
                'from' => $previous,
                'to' => $organization->name,
                'source' => 'master',
            ], $organization->id);
        }

        return redirect()
            ->route('master.organizations.index')
            ->with('sukses', 'Nama workspace '.$organization->name.' disimpan.');
    }

    public function updateLimits(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'ai_monthly_requests' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'storage_mb' => ['nullable', 'integer', 'min:0', 'max:1048576'],
            'admin_limit' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'participant_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'class_limit' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $fields = ['ai_monthly_requests', 'storage_mb', 'admin_limit', 'participant_limit', 'class_limit'];
        $before = $organization->only($fields);

        $organization->update($data);

        AuditLog::record('organization_limits_updated', 'organization', $organization->id, [
            'from' => $before,
            'to' => $organization->only($fields),
        ], $organization->id);

        return back()->with('sukses', 'Kuota workspace '.$organization->name.' disimpan.');
    }

    public function export(Organization $organization): JsonResponse
    {
        $classIds = DB::table('kelas')->where('organization_id', $organization->id)->pluck('id');
        $quizIds = DB::table('quizzes')->where('organization_id', $organization->id)->pluck('id');
        $questionIds = DB::table('questions')->where('organization_id', $organization->id)->pluck('id');
        $sessionIds = DB::table('quiz_sessions')->where('organization_id', $organization->id)->pluck('id');
        $attemptIds = DB::table('attempts')->where('organization_id', $organization->id)->pluck('id');
        $memberIds = DB::table('organization_memberships')->where('organization_id', $organization->id)->pluck('user_id');

        $settings = DB::table('organization_settings')->where('organization_id', $organization->id)->first();
        if ($settings !== null) {
            $settings = (array) $settings;
            unset($settings['ai_api_key'], $settings['image_api_key']);
        }

        $filename = 'organisasi-'.$organization->slug.'-'.now()->format('Ymd-His').'.json';

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'organization' => $organization->toArray(),
            'settings' => $settings,
            'memberships' => DB::table('organization_memberships')->where('organization_id', $organization->id)->get(),
            'users' => User::withoutGlobalScopes()
                ->whereIn('id', $memberIds)
                ->get()
                ->makeHidden(['password', 'remember_token', 'current_session_token'])
                ->toArray(),
            'classes' => DB::table('kelas')->where('organization_id', $organization->id)->get(),
            'class_members' => DB::table('kelas_user')->whereIn('kelas_id', $classIds)->get(),
            'class_quizzes' => DB::table('kelas_quiz')->whereIn('kelas_id', $classIds)->get(),
            'quizzes' => DB::table('quizzes')->where('organization_id', $organization->id)->get(),
            'quiz_questions' => DB::table('quiz_questions')->whereIn('quiz_id', $quizIds)->get(),
            'questions' => DB::table('questions')->where('organization_id', $organization->id)->get(),
            'options' => DB::table('options')->whereIn('question_id', $questionIds)->get(),
            'sessions' => DB::table('quiz_sessions')->where('organization_id', $organization->id)->get(),
            'session_questions' => DB::table('session_questions')->whereIn('quiz_session_id', $sessionIds)->get(),
            'attempts' => DB::table('attempts')->where('organization_id', $organization->id)->get(),
            'attempt_questions' => DB::table('attempt_questions')->whereIn('attempt_id', $attemptIds)->get(),
            'answers' => DB::table('answers')->whereIn('attempt_id', $attemptIds)->get(),
            'events' => DB::table('attempt_events')->whereIn('attempt_id', $attemptIds)->get(),
            'ai_generations' => DB::table('ai_generations')->where('organization_id', $organization->id)->get(),
            'ai_usage' => DB::table('ai_usage_events')->where('organization_id', $organization->id)->get(),
            'participant_login_activities' => DB::table('participant_login_activities')->where('organization_id', $organization->id)->get(),
            'audit_logs' => DB::table('audit_logs')->where('organization_id', $organization->id)->get(),
        ];

        AuditLog::record('organization_exported', 'organization', $organization->id, [
            'filename' => $filename,
        ], $organization->id);

        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function showDelete(Organization $organization): View
    {
        return view('master.organizations.delete', [
            'organization' => $organization,
            'counts' => $this->organizationCounts($organization),
        ]);
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $request->validate([
            'konfirmasi' => ['required', 'string'],
        ], [
            'konfirmasi.required' => 'Ketik slug organisasi untuk konfirmasi.',
        ]);

        if ($request->input('konfirmasi') !== $organization->slug) {
            throw ValidationException::withMessages([
                'konfirmasi' => 'Slug tidak cocok. Ketik '.$organization->slug.' untuk menghapus.',
            ]);
        }

        $counts = $this->organizationCounts($organization);
        $metadata = [
            'name' => $organization->name,
            'slug' => $organization->slug,
            'counts' => $counts,
        ];

        $logoPath = DB::table('organization_settings')->where('organization_id', $organization->id)->value('logo_path');
        $coverPaths = DB::table('kelas')->where('organization_id', $organization->id)->whereNotNull('cover_path')->pluck('cover_path');
        $files = $coverPaths->when($logoPath !== null, fn ($paths) => $paths->push($logoPath))->filter()->unique()->values()->all();

        DB::transaction(function () use ($organization) {
            $sessionIds = DB::table('quiz_sessions')->where('organization_id', $organization->id)->pluck('id');

            DB::table('attempts')->where('organization_id', $organization->id)->delete();
            DB::table('session_questions')->whereIn('quiz_session_id', $sessionIds)->delete();
            DB::table('quiz_sessions')->where('organization_id', $organization->id)->delete();
            DB::table('ai_generations')->where('organization_id', $organization->id)->delete();
            DB::table('questions')->where('organization_id', $organization->id)->delete();
            DB::table('quizzes')->where('organization_id', $organization->id)->delete();
            DB::table('kelas')->where('organization_id', $organization->id)->delete();
            DB::table('participant_login_activities')->where('organization_id', $organization->id)->delete();
            DB::table('ai_usage_events')->where('organization_id', $organization->id)->delete();
            DB::table('audit_logs')->where('organization_id', $organization->id)->delete();
            DB::table('organization_settings')->where('organization_id', $organization->id)->delete();
            DB::table('organization_memberships')->where('organization_id', $organization->id)->delete();
            $organization->delete();
        });

        AuditLog::create([
            'organization_id' => null,
            'actor_id' => auth()->id(),
            'action' => 'organization_deleted',
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
        ]);

        Storage::disk('public')->delete($files);

        return redirect()->route('master.organizations.index')->with('sukses', 'Organisasi '.$metadata['name'].' dihapus.');
    }

    public function updateStatus(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $previous = $organization->is_active;
        $organization->update(['is_active' => $request->boolean('is_active')]);
        AuditLog::record('organization_status_changed', 'organization', $organization->id, [
            'name' => $organization->name,
            'from' => $previous,
            'to' => $organization->is_active,
        ], $organization->id);

        return back()->with(
            'sukses',
            $data['is_active']
                ? 'Organisasi '.$organization->name.' diaktifkan kembali.'
                : 'Organisasi '.$organization->name.' ditangguhkan.',
        );
    }

    /**
     * @return array{classes: int, quizzes: int, sessions: int, attempts: int, members: int}
     */
    private function organizationCounts(Organization $organization): array
    {
        return [
            'classes' => DB::table('kelas')->where('organization_id', $organization->id)->count(),
            'quizzes' => DB::table('quizzes')->where('organization_id', $organization->id)->count(),
            'sessions' => DB::table('quiz_sessions')->where('organization_id', $organization->id)->count(),
            'attempts' => DB::table('attempts')->where('organization_id', $organization->id)->count(),
            'members' => DB::table('organization_memberships')->where('organization_id', $organization->id)->count(),
        ];
    }
}
