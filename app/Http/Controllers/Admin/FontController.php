<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\Font;
use App\Models\OrganizationSetting;
use App\Services\OrganizationUsage;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FontController extends Controller
{
    public function store(Request $request, OrganizationUsage $usage): RedirectResponse
    {
        $organization = app(OrganizationContext::class)->current();
        $organizationId = $organization?->id;

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:60'],
            'berat' => ['required', Rule::in(['300', '400', '500', '600', '700', '800'])],
            'gaya' => ['required', Rule::in(['normal', 'italic'])],
            'berkas' => ['required', 'file', 'extensions:woff2,woff,ttf,otf', 'max:4096'],
        ], [
            'berkas.extensions' => 'Format font harus woff2, woff, ttf, atau otf.',
            'berkas.max' => 'Ukuran file font maksimal 4 MB.',
            'nama.required' => 'Beri nama font agar mudah dikenali di daftar.',
        ]);

        $sudahAda = Font::query()
            ->where('nama', $data['nama'])
            ->where('berat', $data['berat'])
            ->where('gaya', $data['gaya'])
            ->when($organizationId === null, fn ($q) => $q->whereNull('organization_id'))
            ->when($organizationId !== null, fn ($q) => $q->where('organization_id', $organizationId))
            ->exists();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'nama' => 'Font dengan nama, berat, dan gaya yang sama sudah ada.',
            ]);
        }

        $file = $request->file('berkas');

        if ($organizationId !== null && ! $usage->canStore($organizationId, $file->getSize())) {
            throw ValidationException::withMessages([
                'berkas' => 'Kuota penyimpanan workspace tidak cukup untuk font ini.',
            ]);
        }

        $ekstensi = Str::lower($file->getClientOriginalExtension());
        $folder = 'fonts/'.($organizationId ?? 'platform');
        $path = $file->storeAs($folder, Str::uuid()->toString().'.'.$ekstensi, 'public');

        $font = Font::create([
            'organization_id' => $organizationId,
            'nama' => $data['nama'],
            'file_path' => $path,
            'berat' => $data['berat'],
            'gaya' => $data['gaya'],
            'format' => $ekstensi,
            'uploaded_by' => $request->user()->id,
        ]);

        AuditLog::record('font_uploaded', 'font', $font->id, [
            'nama' => $font->nama,
            'berat' => $font->berat,
            'gaya' => $font->gaya,
        ], $organizationId);

        return back()->with('sukses', 'Font '.$font->nama.' ditambahkan ke daftar pilihan.');
    }

    public function destroy(Request $request, Font $font): RedirectResponse
    {
        $organizationId = app(OrganizationContext::class)->current()?->id;

        $boleh = $organizationId === null
            ? $font->organization_id === null
            : $font->organization_id === $organizationId;

        abort_unless($boleh, 404);

        $dipakai = OrganizationSetting::query()
            ->where(fn ($q) => $q->where('font_utama_id', $font->id)->orWhere('font_judul_id', $font->id))
            ->exists()
            || AppSetting::query()
                ->where(fn ($q) => $q->where('font_utama_id', $font->id)->orWhere('font_judul_id', $font->id))
                ->exists();

        if ($dipakai) {
            throw ValidationException::withMessages([
                'font' => 'Font ini masih dipakai. Pilih font lain dulu sebelum menghapus.',
            ]);
        }

        Storage::disk('public')->delete($font->file_path);
        $font->delete();

        AuditLog::record('font_deleted', 'font', $font->id, [
            'nama' => $font->nama,
        ], $organizationId);

        return back()->with('sukses', 'Font '.$font->nama.' dihapus.');
    }
}
