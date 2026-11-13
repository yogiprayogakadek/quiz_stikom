<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Services\OrganizationUsage;
use App\Services\ParticipantLinking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParticipantController extends Controller
{
    public function index(): View
    {
        $organizations = Organization::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('master.participants.index', compact('organizations'));
    }

    public function link(
        Request $request,
        OrganizationUsage $usage,
        ParticipantLinking $linking,
    ): RedirectResponse {
        $data = $request->validate([
            'organization_id' => [
                'required',
                'integer',
                Rule::exists('organizations', 'id')->where('is_active', true),
            ],
            'username' => ['required', 'string', 'max:60'],
            'nama' => ['required', 'string', 'max:120'],
            'allow_name_mismatch' => ['nullable', 'boolean'],
        ], [
            'organization_id.required' => 'Pilih workspace tujuan.',
            'organization_id.exists' => 'Workspace tujuan tidak ditemukan atau tidak aktif.',
            'username.required' => 'NIM wajib diisi.',
            'nama.required' => 'Nama wajib diisi untuk mencocokkan akun.',
        ]);

        $participant = $linking->findExisting($data['username']);

        if ($participant === null || $participant->trashed() || ! $participant->isPeserta()) {
            throw ValidationException::withMessages([
                'username' => 'Akun peserta dengan NIM tersebut tidak ditemukan.',
            ]);
        }

        $organization = Organization::query()->findOrFail($data['organization_id']);
        $nameMatches = $linking->namesMatch($participant, $data['nama']);

        if (! $nameMatches && ! $request->boolean('allow_name_mismatch')) {
            throw ValidationException::withMessages([
                'nama' => 'Nama tidak cocok dengan akun '.$participant->nama.'. Centang konfirmasi nama berbeda untuk melanjutkan.',
            ]);
        }

        if ($linking->isActiveMember($participant, $organization->id)) {
            return redirect()
                ->route('master.participants.index')
                ->with('sukses', 'Peserta '.$participant->nama.' sudah terhubung ke '.$organization->name.'.');
        }

        if (! $usage->canAddParticipant($organization->id)) {
            throw ValidationException::withMessages([
                'organization_id' => 'Kuota peserta workspace penuh ('.$usage->participantLimit($organization->id).').',
            ]);
        }

        $linking->link($participant, $organization, 'master_link', [
            'name_mismatch' => ! $nameMatches,
        ]);

        $pesan = 'Peserta '.$participant->nama.' ditautkan ke '.$organization->name.'.';

        if (blank($participant->email)) {
            $pesan .= ' Akun belum punya email.';
        } elseif (! $participant->hasVerifiedEmail()) {
            $participant->forceFill(['email_verified_at' => now()])->save();
            AuditLog::record('participant_email_verified', 'user', $participant->id, [
                'name' => $participant->nama,
                'username' => $participant->username,
                'source' => 'master_link',
            ], $organization->id);
            $pesan .= ' Email akun ditandai terverifikasi.';
        }

        return redirect()->route('master.participants.index')->with('sukses', $pesan);
    }

    public function verify(Request $request, ParticipantLinking $linking): RedirectResponse
    {
        $data = $request->validateWithBag('verify', [
            'username' => ['required', 'string', 'max:60'],
        ], [
            'username.required' => 'NIM wajib diisi.',
        ]);

        $participant = $linking->findExisting($data['username']);

        if ($participant === null || $participant->trashed() || ! $participant->isPeserta()) {
            throw ValidationException::withMessages([
                'username' => 'Akun peserta dengan NIM tersebut tidak ditemukan.',
            ])->errorBag('verify');
        }

        if (blank($participant->email)) {
            throw ValidationException::withMessages([
                'username' => 'Akun ini belum punya email, jadi tidak ada email yang perlu diverifikasi.',
            ])->errorBag('verify');
        }

        if ($participant->hasVerifiedEmail()) {
            return redirect()
                ->route('master.participants.index')
                ->with('sukses', 'Email '.$participant->username.' sudah terverifikasi.');
        }

        $participant->forceFill(['email_verified_at' => now()])->save();
        AuditLog::record('participant_email_verified', 'user', $participant->id, [
            'name' => $participant->nama,
            'username' => $participant->username,
            'source' => 'master',
        ]);

        return redirect()
            ->route('master.participants.index')
            ->with('sukses', 'Email '.$participant->username.' ditandai terverifikasi.');
    }
}
