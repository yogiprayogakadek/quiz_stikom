<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\OrganizationSetting;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', ['settings' => app('app.settings')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:80'],
            'site_tagline' => ['required', 'string', 'max:160'],
            'organization_name' => ['nullable', 'string', 'max:120'],
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
            'ai_base_url' => ['nullable', 'url', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:500'],
            'ai_default_model' => ['nullable', 'string', 'max:150'],
            'ai_timeout' => ['required', 'integer', 'min:10', 'max:300'],
            'class_cover_mode' => ['required', 'in:local,ai'],
            'image_base_url' => ['nullable', 'url', 'max:255'],
            'image_api_key' => ['nullable', 'string', 'max:500'],
            'image_model' => ['nullable', 'required_if:class_cover_mode,ai', 'string', 'max:150'],
        ], [
            'primary_color.regex' => 'Warna utama harus memakai format heksadesimal, misalnya #2563eb.',
            'logo.max' => 'Ukuran logo maksimal 2 MB.',
        ]);

        $organizationId = app(OrganizationContext::class)->id();
        $settings = $organizationId === null
            ? AppSetting::query()->firstOrNew(['id' => 1])
            : OrganizationSetting::query()->firstOrNew(['organization_id' => $organizationId]);

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
