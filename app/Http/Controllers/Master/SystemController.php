<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function updateMaintenance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'maintenance_enabled' => ['required', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
        ]);

        $settings = AppSetting::current();
        $settings->id ??= 1;
        $enabled = $request->boolean('maintenance_enabled');
        $settings->forceFill([
            'maintenance_enabled' => $enabled,
            'maintenance_message' => filled($data['maintenance_message'] ?? null)
                ? trim($data['maintenance_message'])
                : 'Sistem sedang menjalani pemeliharaan. Silakan coba kembali beberapa saat lagi.',
            'maintenance_started_at' => $enabled ? ($settings->maintenance_started_at ?? now()) : null,
        ])->save();

        return back()->with(
            'sukses',
            $enabled ? 'Mode maintenance diaktifkan.' : 'Sistem dibuka kembali untuk pengguna.',
        );
    }
}
