<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\User;
use App\Services\OrganizationUsage;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SettingController extends Controller
{
    private const SECTIONS = ['identitas', 'workspace', 'legal', 'peserta', 'ai', 'cover'];

    public function edit(OrganizationUsage $usage): View
    {
        $organization = app(OrganizationContext::class)->current();
        $activeTab = request('tab', session('settings_tab', 'identitas'));

        if (! in_array($activeTab, self::SECTIONS, true)) {
            $activeTab = 'identitas';
        }

        return view('admin.settings.edit', [
            'settings' => app('app.settings'),
            'usage' => $organization ? $usage->summary($organization->id) : null,
            'isOwner' => $this->isOwner(request()->user(), $organization),
            'activeTab' => $activeTab,
        ]);
    }

    public function update(Request $request, OrganizationUsage $usage): RedirectResponse
    {
        $organization = app(OrganizationContext::class)->current();
        $section = $request->input('_section');

        if ($section !== null && ! in_array($section, self::SECTIONS, true)) {
            throw ValidationException::withMessages([
                '_section' => 'Kategori pengaturan tidak dikenal.',
            ]);
        }

        $sections = $section === null ? self::SECTIONS : [$section];
        $owner = $organization !== null && $this->isOwner($request->user(), $organization);
        $rules = [];

        if (in_array('identitas', $sections, true)) {
            $rules += [
                'site_name' => ['required', 'string', 'max:80'],
                'site_tagline' => ['required', 'string', 'max:160'],
                'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/', function (string $attribute, mixed $value, \Closure $fail) {
                    if (! is_string($value) || ! preg_match('/^#([0-9A-Fa-f]{6})$/', $value, $matches)) {
                        return;
                    }
                    $rgb = array_map(fn ($hex) => hexdec($hex) / 255, str_split($matches[1], 2));
                    $linear = array_map(fn ($channel) => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4, $rgb);
                    $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
                    if (1.05 / ($luminance + 0.05) < 4.5) {
                        $fail('Pilih warna utama yang lebih gelap agar teks putih tetap mudah dibaca.');
                    }
                }],
                'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
                'login_title' => ['required', 'string', 'max:100'],
                'login_description' => ['required', 'string', 'max:300'],
                'login_layout' => ['required', 'in:split,centered'],
            ];
        }

        if (in_array('workspace', $sections, true)) {
            $rules['locale'] = ['required', 'in:id,en'];

            if ($organization !== null) {
                $rules['organization_slug'] = [
                    'required', 'string', 'max:80', 'alpha_dash',
                    Rule::unique('organizations', 'slug')->ignore($organization->id),
                ];
                $rules['organization_timezone'] = ['required', 'timezone:all'];

                if ($owner) {
                    $rules['organization_name'] = ['required', 'string', 'max:120'];
                }
            }
        }

        if (in_array('legal', $sections, true) && $organization !== null && $owner) {
            $rules['terms_content'] = ['nullable', 'string', 'max:20000'];
            $rules['privacy_content'] = ['nullable', 'string', 'max:20000'];
        }

        if (in_array('peserta', $sections, true)) {
            $rules['allow_pin_enrollment'] = ['boolean'];
        }

        if (in_array('ai', $sections, true)) {
            $rules += [
                'ai_base_url' => ['nullable', 'url', 'max:255'],
                'ai_api_key' => ['nullable', 'string', 'max:500'],
                'ai_default_model' => ['nullable', 'string', 'max:150'],
                'ai_timeout' => ['required', 'integer', 'min:10', 'max:300'],
            ];
        }

        if (in_array('cover', $sections, true)) {
            $rules += [
                'class_cover_mode' => ['required', 'in:local,ai'],
                'image_base_url' => ['nullable', 'url', 'max:255'],
                'image_api_key' => ['nullable', 'string', 'max:500'],
                'image_model' => ['nullable', 'required_if:class_cover_mode,ai', 'string', 'max:150'],
            ];
        }

        $data = $request->validate($rules, [
            'primary_color.regex' => 'Warna utama harus memakai format heksadesimal, misalnya #2563eb.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
            'organization_slug.unique' => 'Slug ini sudah dipakai organisasi lain.',
        ]);

        if (in_array('peserta', $sections, true)) {
            $data['allow_pin_enrollment'] = $request->boolean('allow_pin_enrollment');
        }

        $organizationId = $organization?->id;
        $settings = $organizationId === null
            ? AppSetting::query()->firstOrNew(['id' => 1])
            : OrganizationSetting::query()->firstOrNew(['organization_id' => $organizationId]);

        if (in_array('identitas', $sections, true)
            && $organizationId !== null
            && $request->hasFile('logo')
            && ! $usage->canStore($organizationId, $request->file('logo')->getSize(), $settings->logo_path)) {
            throw ValidationException::withMessages([
                'logo' => 'Kuota penyimpanan workspace tidak cukup untuk logo ini.',
            ]);
        }

        if (in_array('workspace', $sections, true) && $organization !== null) {
            $organization->update([
                'slug' => Str::lower($data['organization_slug']),
                'timezone' => $data['organization_timezone'],
            ]);
            unset($data['organization_slug'], $data['organization_timezone']);

            if (isset($data['organization_name']) && $data['organization_name'] !== $organization->name) {
                AuditLog::record('organization_renamed', 'organization', $organization->id, [
                    'from' => $organization->name,
                    'to' => $data['organization_name'],
                    'source' => 'owner',
                ], $organization->id);
                $organization->update(['name' => $data['organization_name']]);
            }
            unset($data['organization_name']);
        }

        if (in_array('legal', $sections, true)) {
            foreach (['terms_content', 'privacy_content'] as $legalField) {
                if (array_key_exists($legalField, $data)) {
                    $data[$legalField] = filled($data[$legalField]) ? trim($data[$legalField]) : null;
                }
            }
        }

        if (in_array('identitas', $sections, true) && $request->hasFile('logo')) {
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('branding', 'public');
        }

        unset($data['logo']);

        foreach (['ai_api_key', 'image_api_key'] as $secret) {
            if (blank($data[$secret] ?? null)) {
                unset($data[$secret]);
            }
        }

        if ($data !== []) {
            $settings->fill($data)->save();
        }

        $labels = [
            'identitas' => 'Identitas',
            'workspace' => 'Workspace',
            'legal' => 'Dokumen legal',
            'peserta' => 'Kebijakan peserta',
            'ai' => 'Provider AI',
            'cover' => 'Cover kelas',
        ];

        $message = $section === null ? 'Pengaturan publik diperbarui.' : $labels[$section].' diperbarui.';

        return back()
            ->with('sukses', $message)
            ->with('settings_tab', $section ?? 'identitas');
    }

    private function isOwner(?User $user, ?Organization $organization): bool
    {
        return $organization !== null && $user !== null && $user->organizations()
            ->whereKey($organization->id)
            ->wherePivot('role', 'owner')
            ->wherePivot('status', 'active')
            ->exists();
    }
}
