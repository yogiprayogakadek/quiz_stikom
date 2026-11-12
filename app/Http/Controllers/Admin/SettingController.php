<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\OrganizationSetting;
use App\Services\FontLibrary;
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
    public function edit(OrganizationUsage $usage): View
    {
        $organization = app(OrganizationContext::class)->current();

        return view('admin.settings.edit', [
            'settings' => app('app.settings'),
            'usage' => $organization ? $usage->summary($organization->id) : null,
            'fonts' => app(FontLibrary::class)->options($organization?->id),
        ]);
    }

    public function update(Request $request, OrganizationUsage $usage): RedirectResponse
    {
        $organization = app(OrganizationContext::class)->current();

        $rules = [
            'site_name' => ['required', 'string', 'max:80'],
            'site_tagline' => ['required', 'string', 'max:160'],
            'organization_name' => ['nullable', 'string', 'max:120'],
            'font_utama_id' => ['nullable', 'integer'],
            'font_judul_id' => ['nullable', 'integer'],
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
            'locale' => ['required', 'in:id,en'],
            'allow_pin_enrollment' => ['boolean'],
            'ai_base_url' => ['nullable', 'url', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
            'ai_default_model' => ['nullable', 'string', 'max:150'],
            'ai_timeout' => ['required', 'integer', 'min:10', 'max:300'],
            'class_cover_mode' => ['required', 'in:local,ai'],
            'image_base_url' => ['nullable', 'url', 'max:255'],
            'image_api_key' => ['nullable', 'string', 'max:500'],
            'image_model' => ['nullable', 'required_if:class_cover_mode,ai', 'string', 'max:150'],
        ];

        if ($organization !== null) {
            $rules['organization_slug'] = [
                'required', 'string', 'max:80', 'alpha_dash',
                Rule::unique('organizations', 'slug')->ignore($organization->id),
            ];
            $rules['organization_timezone'] = ['required', 'timezone:all'];
        }

        $data = $request->validate($rules, [
            'primary_color.regex' => 'Warna utama harus memakai format heksadesimal, misalnya #2563eb.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
            'organization_slug.unique' => 'Slug ini sudah dipakai organisasi lain.',
        ]);

        $data['allow_pin_enrollment'] = $request->boolean('allow_pin_enrollment');

        $library = app(FontLibrary::class);
        $library->assertUsable($data['font_utama_id'] ?? null, $organization?->id, 'font_utama_id');
        $library->assertUsable($data['font_judul_id'] ?? null, $organization?->id, 'font_judul_id');

        $organizationId = $organization?->id;
        $settings = $organizationId === null
            ? AppSetting::query()->firstOrNew(['id' => 1])
            : OrganizationSetting::query()->firstOrNew(['organization_id' => $organizationId]);

        if ($organizationId !== null && $request->hasFile('logo')
            && ! $usage->canStore($organizationId, $request->file('logo')->getSize(), $settings->logo_path)) {
            throw ValidationException::withMessages([
                'logo' => 'Kuota penyimpanan workspace tidak cukup untuk logo ini.',
            ]);
        }

        if ($organization !== null) {
            $organization->update([
                'slug' => Str::lower($data['organization_slug']),
                'timezone' => $data['organization_timezone'],
            ]);
            unset($data['organization_slug'], $data['organization_timezone']);
        }

        if ($request->hasFile('logo')) {
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

        $settings->fill($data)->save();

        return back()->with('sukses', 'Pengaturan publik diperbarui.');
    }
}
